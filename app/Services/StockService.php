<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\RestockEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Atomically adjust stock with row-level locking, negative stock prevention,
     * and immutable movement ledger entry.
     */
    public function adjustStock(
        Product $product,
        int $quantityDelta,
        string $movementType,
        ?string $reason = null,
        ?Order $order = null,
        ?User $user = null,
        ?PurchaseOrder $purchaseOrder = null,
        ?PurchaseOrderReceipt $purchaseOrderReceipt = null
    ): InventoryMovement {
        if ($quantityDelta === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity change cannot be zero.',
            ]);
        }

        return DB::transaction(function () use (
            $product,
            $quantityDelta,
            $movementType,
            $reason,
            $order,
            $user,
            $purchaseOrder,
            $purchaseOrderReceipt
        ) {
            // OBS-03: Validate PurchaseOrder & PurchaseOrderReceipt integrity inside transaction.
            if ($purchaseOrder !== null || $purchaseOrderReceipt !== null) {
                if ($purchaseOrder === null || $purchaseOrderReceipt === null) {
                    throw ValidationException::withMessages([
                        'purchase_order' => 'Both a valid purchase order and receipt are required for PO stock movements.',
                    ]);
                }

                if ($movementType !== InventoryMovement::TYPE_RESTOCK || $quantityDelta <= 0) {
                    throw ValidationException::withMessages([
                        'movement_type' => 'Purchase order receipt stock movements must use positive restock quantities.',
                    ]);
                }

                if ((int) $purchaseOrderReceipt->purchase_order_id !== (int) $purchaseOrder->id) {
                    throw ValidationException::withMessages([
                        'purchase_order_receipt' => 'The supplied receipt does not belong to the supplied purchase order.',
                    ]);
                }

                if (!in_array($purchaseOrder->status, [
                    PurchaseOrder::STATUS_ORDERED,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'purchase_order' => 'Purchase order is not in an eligible state for receiving stock.',
                    ]);
                }
            }

            /** @var Product $lockedProduct */
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            if ($purchaseOrderReceipt !== null) {
                $alreadyApplied = InventoryMovement::where('purchase_order_receipt_id', $purchaseOrderReceipt->id)
                    ->where('product_id', $lockedProduct->id)
                    ->exists();

                if ($alreadyApplied) {
                    throw ValidationException::withMessages([
                        'purchase_order_receipt' => 'Stock movement for this receipt item has already been applied.',
                    ]);
                }
            }

            $previousStock = $lockedProduct->stock_quantity;
            $newStock = $previousStock + $quantityDelta;

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'stock' => "Cannot deduct {$quantityDelta} items. Available stock is {$previousStock}. Negative stock is not allowed.",
                ]);
            }

            // Update product stock
            $lockedProduct->stock_quantity = $newStock;
            $lockedProduct->save();

            // Record immutable inventory movement
            $movement = InventoryMovement::create([
                'product_id' => $lockedProduct->id,
                'movement_type' => $movementType,
                'quantity' => $quantityDelta,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reason' => $reason,
                'order_id' => $order?->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'purchase_order_receipt_id' => $purchaseOrderReceipt?->id,
                'user_id' => $user?->id ?? auth()->id(),
            ]);

            // Audit log
            AuditLog::log('inventory_movement', $lockedProduct, [
                'stock_quantity' => $previousStock,
            ], [
                'stock_quantity' => $newStock,
                'delta' => $quantityDelta,
                'type' => $movementType,
                'reason' => $reason,
                'purchase_order_id' => $purchaseOrder?->id,
                'purchase_order_receipt_id' => $purchaseOrderReceipt?->id,
            ]);

            // Sync automatic low-stock alert lifecycle inside the same transaction
            app(LowStockAlertService::class)->syncForProduct($lockedProduct, $movementType);

            // Sync back-in-stock alert lifecycle when product transitions from unavailable to available
            $this->recordRestockEventIfQualifying($lockedProduct, $previousStock, $newStock, $quantityDelta);

            return $movement;
        });
    }

    /**
     * Restore stock when an order is cancelled, refused, or returned.
     * Prevents duplicate restoration!
     */
    public function restoreOrderStock(
        Order $order,
        string $reason,
        string $movementType = InventoryMovement::TYPE_CANCELLATION_RESTOCK,
        ?User $user = null
    ): array {
        return DB::transaction(function () use ($order, $reason, $movementType, $user) {
            // Check if this order has already had its stock restored
            $alreadyRestored = InventoryMovement::where('order_id', $order->id)
                ->whereIn('movement_type', [
                    InventoryMovement::TYPE_CANCELLATION_RESTOCK,
                    InventoryMovement::TYPE_CUSTOMER_RETURN,
                ])
                ->exists();

            if ($alreadyRestored) {
                throw ValidationException::withMessages([
                    'order' => "Stock for order #{$order->order_number} has already been restored. Duplicate restoration is prevented.",
                ]);
            }

            $movements = [];
            $order->loadMissing('items.product');

            foreach ($order->items as $item) {
                if (!$item->product_id) {
                    continue; // Product may have been deleted, skip safely
                }

                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                if (!$product) {
                    continue;
                }

                $previousStock = $product->stock_quantity;
                $newStock = $previousStock + $item->quantity;

                $product->stock_quantity = $newStock;
                $product->save();

                $movement = InventoryMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => $movementType,
                    'quantity' => $item->quantity, // positive restoration
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reason' => $reason . " (Order #{$order->order_number})",
                    'order_id' => $order->id,
                    'user_id' => $user?->id ?? auth()->id(),
                ]);

                $movements[] = $movement;

                AuditLog::log('stock_restored', $product, [
                    'stock_quantity' => $previousStock,
                ], [
                    'stock_quantity' => $newStock,
                    'order_number' => $order->order_number,
                    'restored_quantity' => $item->quantity,
                ]);

                app(LowStockAlertService::class)->syncForProduct($product, $movementType);

                // Sync back-in-stock alert lifecycle when product transitions from unavailable to available
                $this->recordRestockEventIfQualifying($product, $previousStock, $newStock, $item->quantity);
            }

            return $movements;
        });
    }

    /**
     * Hook to detect and record a back-in-stock restock event if product transitioned from unavailable to available.
     */
    protected function recordRestockEventIfQualifying(
        Product $product,
        int $previousStock,
        int $newStock,
        int $quantityDelta
    ): ?RestockEvent {
        return app(BackInStockService::class)->recordRestockEventIfQualifying(
            $product,
            $previousStock,
            $newStock,
            $quantityDelta
        );
    }
}

