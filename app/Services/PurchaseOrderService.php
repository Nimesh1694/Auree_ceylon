<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function generatePoNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "PO-{$year}-";

        $latest = PurchaseOrder::where('po_number', 'like', "{$prefix}%")
            ->orderByDesc('po_number')
            ->lockForUpdate()
            ->value('po_number');

        $next = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $exists = PurchaseOrder::where('po_number', $candidate)->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    public function generateReceiptNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "POR-{$year}-";

        $latest = PurchaseOrderReceipt::where('receipt_number', 'like', "{$prefix}%")
            ->orderByDesc('receipt_number')
            ->lockForUpdate()
            ->value('receipt_number');

        $next = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $exists = PurchaseOrderReceipt::where('receipt_number', $candidate)->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Create a new draft Purchase Order with server-authoritative totals and snapshots.
     */
    public function createDraft(array $data, User $actor, bool $canManageCosts = true): PurchaseOrder
    {
        $maxAttempts = 3;
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(function () use ($data, $actor, $canManageCosts) {
                    /** @var Supplier $supplier */
                    $supplier = Supplier::where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();

                    if ($supplier->status !== Supplier::STATUS_ACTIVE) {
                        throw ValidationException::withMessages([
                            'supplier_id' => 'Only active suppliers can be selected for new purchase orders.',
                        ]);
                    }

                    $prepared = $this->prepareItemsAndTotals(
                        $supplier,
                        $data['items'],
                        $canManageCosts ? ($data['tax_amount'] ?? '0') : '0',
                        $canManageCosts ? ($data['shipping_cost'] ?? '0') : '0',
                        null,
                        $canManageCosts
                    );

                    $po = PurchaseOrder::create([
                        'po_number' => $this->generatePoNumber(),
                        'supplier_id' => $supplier->id,
                        'status' => PurchaseOrder::STATUS_DRAFT,
                        'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                        'subtotal' => $prepared['subtotal'],
                        'tax_amount' => $prepared['tax_amount'],
                        'shipping_cost' => $prepared['shipping_cost'],
                        'total_cost' => $prepared['total_cost'],
                        'notes' => $data['notes'] ?? null,
                        'created_by' => $actor->id,
                    ]);

                    foreach ($prepared['items'] as $line) {
                        PurchaseOrderItem::create(array_merge($line, [
                            'purchase_order_id' => $po->id,
                        ]));
                    }

                    AuditLog::log('purchase_order_created', $po, null, [
                        'po_number' => $po->po_number,
                        'supplier_id' => $supplier->id,
                        'status' => $po->status,
                        'total_cost' => $po->total_cost,
                        'items_count' => count($prepared['items']),
                    ]);

                    return $po->load('items', 'supplier');
                });
            } catch (QueryException $e) {
                if ($this->isPoNumberDuplicateException($e) && $attempt < $maxAttempts) {
                    usleep(random_int(10000, 40000));
                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * Determine whether a QueryException is specifically a po_number unique constraint collision.
     */
    public function isPoNumberDuplicateException(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        if ($sqlState !== '23000' && !str_contains($e->getMessage(), '23000')) {
            return false;
        }

        $message = $e->getMessage();

        // MySQL 8.0: Error 1062 duplicate entry for key 'purchase_orders_po_number_unique'
        if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
            return str_contains($message, 'purchase_orders_po_number_unique');
        }

        // SQLite: UNIQUE constraint failed: purchase_orders.po_number
        if (str_contains($message, 'UNIQUE constraint failed: purchase_orders.po_number')) {
            return true;
        }

        return false;
    }

    /**
     * Update an existing draft Purchase Order.
     */
    public function updateDraft(PurchaseOrder $po, array $data, User $actor, bool $canManageCosts = true): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data, $canManageCosts) {
            /** @var PurchaseOrder $lockedPo */
            $lockedPo = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if ($lockedPo->status !== PurchaseOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft purchase orders can be edited.',
                ]);
            }

            /** @var Supplier $supplier */
            $supplier = Supplier::where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();

            if ($supplier->status !== Supplier::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Only active suppliers can be selected for purchase orders.',
                ]);
            }

            $oldValues = [
                'supplier_id' => $lockedPo->supplier_id,
                'total_cost' => (string) $lockedPo->total_cost,
                'expected_delivery_date' => $lockedPo->expected_delivery_date?->toDateString(),
            ];

            $prepared = $this->prepareItemsAndTotals(
                $supplier,
                $data['items'],
                $canManageCosts ? ($data['tax_amount'] ?? '0') : (string) $lockedPo->tax_amount,
                $canManageCosts ? ($data['shipping_cost'] ?? '0') : (string) $lockedPo->shipping_cost,
                $lockedPo,
                $canManageCosts
            );

            $lockedPo->update([
                'supplier_id' => $supplier->id,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => $prepared['subtotal'],
                'tax_amount' => $prepared['tax_amount'],
                'shipping_cost' => $prepared['shipping_cost'],
                'total_cost' => $prepared['total_cost'],
                'notes' => $data['notes'] ?? null,
            ]);

            $lockedPo->items()->delete();
            foreach ($prepared['items'] as $line) {
                PurchaseOrderItem::create(array_merge($line, [
                    'purchase_order_id' => $lockedPo->id,
                ]));
            }

            AuditLog::log('purchase_order_updated', $lockedPo, $oldValues, [
                'supplier_id' => $supplier->id,
                'total_cost' => $lockedPo->total_cost,
                'expected_delivery_date' => $lockedPo->expected_delivery_date?->toDateString(),
                'items_count' => count($prepared['items']),
            ]);

            return $lockedPo->fresh(['items', 'supplier']);
        });
    }

    /**
     * Owner-only approval: transition Draft -> Ordered.
     */
    public function approve(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor) {
            /** @var PurchaseOrder $lockedPo */
            $lockedPo = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if ($lockedPo->status !== PurchaseOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft purchase orders can be approved.',
                ]);
            }

            if (!$lockedPo->items()->exists()) {
                throw ValidationException::withMessages([
                    'items' => 'Cannot approve a purchase order with no items.',
                ]);
            }

            $oldStatus = $lockedPo->status;

            $lockedPo->update([
                'status' => PurchaseOrder::STATUS_ORDERED,
                'approved_by' => $actor->id,
                'ordered_at' => now(),
            ]);

            AuditLog::log('purchase_order_approved', $lockedPo, [
                'status' => $oldStatus,
            ], [
                'status' => $lockedPo->status,
                'po_number' => $lockedPo->po_number,
                'approved_by' => $actor->id,
                'total_cost' => $lockedPo->total_cost,
            ]);

            return $lockedPo->fresh(['items', 'supplier']);
        });
    }

    /**
     * Cancel a Draft or Ordered Purchase Order (strictly blocked once any item is received).
     */
    public function cancel(PurchaseOrder $po, string $reason, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $reason, $actor) {
            /** @var PurchaseOrder $lockedPo */
            $lockedPo = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedPo->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft or unreceived ordered purchase orders can be cancelled.',
                ]);
            }

            $receivedTotal = (int) $lockedPo->items()->lockForUpdate()->sum('received_qty');
            if ($receivedTotal > 0 || $lockedPo->receipts()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Cannot cancel a purchase order that has already received stock.',
                ]);
            }

            $oldStatus = $lockedPo->status;

            $lockedPo->update([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
            ]);

            AuditLog::log('purchase_order_cancelled', $lockedPo, [
                'status' => $oldStatus,
            ], [
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'cancellation_reason' => $lockedPo->cancellation_reason,
            ]);

            return $lockedPo->fresh();
        });
    }

    /**
     * Record a partial or full stock receipt against an Ordered/Partially Received PO.
     * Routes all stock additions through StockService::adjustStock() and updates WAC.
     *
     * @param array<int, array{purchase_order_item_id: int, quantity_received: int}> $incomingItems
     */
    public function receive(PurchaseOrder $po, array $incomingItems, ?string $notes, User $actor): PurchaseOrderReceipt
    {
        $filtered = collect($incomingItems)
            ->filter(fn ($row) => (int) ($row['quantity_received'] ?? 0) > 0)
            ->values();

        if ($filtered->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Enter a positive received quantity for at least one line item.',
            ]);
        }

        return DB::transaction(function () use ($po, $filtered, $notes, $actor) {
            /** @var PurchaseOrder $lockedPo */
            $lockedPo = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedPo->status, [
                PurchaseOrder::STATUS_ORDERED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only ordered or partially received purchase orders can receive stock.',
                ]);
            }

            $poItems = PurchaseOrderItem::where('purchase_order_id', $lockedPo->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $receipt = PurchaseOrderReceipt::create([
                'receipt_number' => $this->generateReceiptNumber(),
                'purchase_order_id' => $lockedPo->id,
                'received_by' => $actor->id,
                'received_at' => now(),
                'notes' => $notes,
            ]);

            $totalIncomingUnits = 0;

            foreach ($filtered as $row) {
                $itemId = (int) $row['purchase_order_item_id'];
                $qtyReceived = (int) $row['quantity_received'];

                /** @var PurchaseOrderItem|null $poItem */
                $poItem = $poItems->get($itemId);
                if (!$poItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Invalid purchase order item specified for receiving.',
                    ]);
                }

                $remainingQty = $poItem->ordered_qty - $poItem->received_qty;
                if ($qtyReceived > $remainingQty) {
                    throw ValidationException::withMessages([
                        'items' => "Cannot receive {$qtyReceived} units for '{$poItem->product_name}'. Only {$remainingQty} remaining.",
                    ]);
                }

                /** @var Product $lockedProduct */
                $lockedProduct = Product::where('id', $poItem->product_id)->lockForUpdate()->firstOrFail();

                $existingStock = max(0, (int) $lockedProduct->stock_quantity);
                $existingCost = $lockedProduct->cost_price !== null ? (string) $lockedProduct->cost_price : null;
                $receivedUnitCost = (string) $poItem->unit_cost;

                $newWac = $this->calculateWeightedAverageCost(
                    $existingStock,
                    $existingCost,
                    $qtyReceived,
                    $receivedUnitCost
                );

                // Create immutable receipt line item
                PurchaseOrderReceiptItem::create([
                    'purchase_order_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $lockedProduct->id,
                    'quantity_received' => $qtyReceived,
                    'unit_cost' => $receivedUnitCost,
                ]);

                // Apply stock exclusively via StockService::adjustStock()
                $this->stockService->adjustStock(
                    product: $lockedProduct,
                    quantityDelta: $qtyReceived,
                    movementType: InventoryMovement::TYPE_RESTOCK,
                    reason: "PO #{$lockedPo->po_number} Receipt #{$receipt->receipt_number}",
                    order: null,
                    user: $actor,
                    purchaseOrder: $lockedPo,
                    purchaseOrderReceipt: $receipt
                );

                // Update product Weighted Average Cost (historical order_items.cost_price remains untouched)
                $lockedProduct->refresh();
                $lockedProduct->cost_price = $newWac;
                $lockedProduct->save();

                // Update PO item received_qty
                $poItem->received_qty += $qtyReceived;
                $poItem->save();

                // Update supplier-product last_purchased_at if link still exists
                $link = ProductSupplier::where('product_id', $lockedProduct->id)
                    ->where('supplier_id', $lockedPo->supplier_id)
                    ->first();
                if ($link) {
                    $link->last_purchased_at = now();
                    $link->save();
                }

                $totalIncomingUnits += $qtyReceived;
            }

            $allReceived = $poItems->every(fn (PurchaseOrderItem $item) => $item->received_qty >= $item->ordered_qty);
            $oldStatus = $lockedPo->status;
            $newStatus = $allReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;

            $lockedPo->status = $newStatus;
            if ($allReceived) {
                $lockedPo->received_at = now();
            }
            $lockedPo->save();

            $auditAction = $allReceived ? 'purchase_order_received' : 'purchase_order_partially_received';
            AuditLog::log($auditAction, $lockedPo, [
                'status' => $oldStatus,
            ], [
                'status' => $newStatus,
                'receipt_number' => $receipt->receipt_number,
                'units_received' => $totalIncomingUnits,
            ]);

            return $receipt->load('items');
        });
    }

    /**
     * Calculate Weighted Average Cost using BCMath decimal arithmetic.
     */
    public function calculateWeightedAverageCost(
        int $existingStock,
        ?string $existingAverageCost,
        int $receivedQty,
        string $receivedUnitCost
    ): string {
        $receivedUnitCostNorm = $this->roundDecimal($receivedUnitCost, 2);

        if ($existingStock <= 0 || $existingAverageCost === null) {
            return $receivedUnitCostNorm;
        }

        $existingValue = bcmul((string) $existingStock, $existingAverageCost, 6);
        $incomingValue = bcmul((string) $receivedQty, $receivedUnitCostNorm, 6);
        $totalValue = bcadd($existingValue, $incomingValue, 6);
        $totalQty = (string) ($existingStock + $receivedQty);
        $average = bcdiv($totalValue, $totalQty, 6);

        return $this->roundDecimal($average, 2);
    }

    private function roundDecimal(string $value, int $scale = 2): string
    {
        $factor = '0.' . str_repeat('0', $scale) . '5';
        if (bccomp($value, '0', 6) < 0) {
            return bcsub($value, $factor, $scale);
        }

        return bcadd($value, $factor, $scale);
    }

    /**
     * Validate supplier-product relationships and calculate server-authoritative PO totals.
     *
     * @param array<int, array{product_id: int, ordered_qty: int, unit_cost?: string|float|int|null}> $rawItems
     */
    private function prepareItemsAndTotals(
        Supplier $supplier,
        array $rawItems,
        mixed $taxAmount,
        mixed $shippingCost,
        ?PurchaseOrder $existingPo = null,
        bool $canManageCosts = true
    ): array {
        if (empty($rawItems)) {
            throw ValidationException::withMessages([
                'items' => 'At least one product item is required.',
            ]);
        }

        $existingItemsByProduct = $existingPo
            ? $existingPo->items()->get()->keyBy('product_id')
            : collect();

        $seenProductIds = [];
        $preparedItems = [];
        $subtotal = '0.00';

        foreach ($rawItems as $index => $row) {
            $productId = (int) $row['product_id'];
            $orderedQty = (int) $row['ordered_qty'];

            if (in_array($productId, $seenProductIds, true)) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Duplicate product in purchase order lines.',
                ]);
            }
            $seenProductIds[] = $productId;

            if ($orderedQty <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.ordered_qty" => 'Ordered quantity must be at least 1.',
                ]);
            }

            /** @var Product|null $product */
            $product = Product::where('id', $productId)->where('is_active', true)->first();
            if (!$product) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Selected product is inactive or does not exist.',
                ]);
            }

            /** @var ProductSupplier|null $link */
            $link = ProductSupplier::where('product_id', $product->id)
                ->where('supplier_id', $supplier->id)
                ->first();

            if (!$link) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => "Product '{$product->name_en}' is not linked to supplier '{$supplier->name}'. Link it in Supplier Management first.",
                ]);
            }

            if ($canManageCosts) {
                $unitCostRaw = isset($row['unit_cost']) && $row['unit_cost'] !== '' && $row['unit_cost'] !== null
                    ? (string) $row['unit_cost']
                    : (string) $link->unit_cost;
            } else {
                // FND-04: Ignore submitted unit_cost from users without cost permission; preserve DB cost.
                $existingLine = $existingItemsByProduct->get($product->id);
                $unitCostRaw = ($existingLine && (int) $existingPo?->supplier_id === (int) $supplier->id)
                    ? (string) $existingLine->unit_cost
                    : (string) $link->unit_cost;
            }

            if (!is_numeric($unitCostRaw) || bccomp($unitCostRaw, '0', 4) < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_cost" => 'Unit cost must be a non-negative decimal.',
                ]);
            }

            $unitCost = $this->roundDecimal($unitCostRaw, 2);
            $lineTotal = bcmul($unitCost, (string) $orderedQty, 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);

            $preparedItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name_en,
                'product_sku' => $product->sku,
                'supplier_sku' => $link->supplier_sku,
                'ordered_qty' => $orderedQty,
                'received_qty' => 0,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ];
        }

        $tax = $this->roundDecimal((string) ($taxAmount ?: '0'), 2);
        $shipping = $this->roundDecimal((string) ($shippingCost ?: '0'), 2);
        $totalCost = bcadd(bcadd($subtotal, $tax, 2), $shipping, 2);

        return [
            'items' => $preparedItems,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'shipping_cost' => $shipping,
            'total_cost' => $totalCost,
        ];
    }
}

