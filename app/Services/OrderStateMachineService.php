<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CodCollection;
use App\Models\Delivery;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStateMachineService
{
    public const STATUS_PLACED = 'placed';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PACKED = 'packed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CASH_COLLECTED = 'cash_collected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUSED = 'refused';
    public const STATUS_RETURNED = 'returned';

    /**
     * Map of allowed state transitions.
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_PLACED => [
            self::STATUS_CONFIRMED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_CONFIRMED => [
            self::STATUS_PACKED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_PACKED => [
            self::STATUS_SHIPPED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_SHIPPED => [
            self::STATUS_DELIVERED,
            self::STATUS_REFUSED,
        ],
        self::STATUS_DELIVERED => [
            self::STATUS_CASH_COLLECTED,
            self::STATUS_RETURNED,
        ],
        self::STATUS_CASH_COLLECTED => [
            self::STATUS_COMPLETED,
            self::STATUS_RETURNED,
        ],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_REFUSED => [],
        self::STATUS_RETURNED => [],
    ];

    public function __construct(
        private readonly StockService $stockService
    ) {}

    /**
     * Check if a transition is valid.
     */
    public function canTransition(string $currentStatus, string $newStatus): bool
    {
        return in_array($newStatus, self::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true);
    }

    /**
     * Execute order status transition with validation, stock handling, and audit logging.
     */
    public function transition(
        Order $order,
        string $newStatus,
        ?string $reason = null,
        ?User $user = null,
        array $extraData = []
    ): Order {
        $currentStatus = $order->status;

        if (!$this->canTransition($currentStatus, $newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Invalid order status transition from '{$currentStatus}' to '{$newStatus}'.",
            ]);
        }

        return DB::transaction(function () use ($order, $currentStatus, $newStatus, $reason, $user, $extraData) {
            $user = $user ?? auth()->user();

            // 1. Specific checks per target status
            if ($newStatus === self::STATUS_SHIPPED) {
                // Courier assignment check
                $order->loadMissing('delivery');
                $courierName = $extraData['courier_name'] ?? $order->delivery?->courier_name;
                $trackingNumber = $extraData['tracking_number'] ?? $order->delivery?->tracking_number;

                if (empty($courierName) || empty($trackingNumber)) {
                    throw ValidationException::withMessages([
                        'courier' => 'A courier name and tracking number are required before marking order as shipped.',
                    ]);
                }

                $order->delivery()->update([
                    'courier_name' => $courierName,
                    'tracking_number' => $trackingNumber,
                    'status' => 'in_transit',
                    'dispatched_at' => now(),
                    'notes' => $extraData['delivery_notes'] ?? $order->delivery?->notes,
                ]);
            }

            if ($newStatus === self::STATUS_DELIVERED) {
                $order->delivery()->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);
            }

            if ($newStatus === self::STATUS_CASH_COLLECTED) {
                // Ensure COD collection record exists and is reconciled
                $cod = $order->codCollection;
                if (!$cod || $cod->reconciliation_status === CodCollection::STATUS_PENDING) {
                    throw ValidationException::withMessages([
                        'cod' => 'Cannot mark as cash collected without a reconciled COD collection record.',
                    ]);
                }

                if ($cod->collected_amount <= 0) {
                    throw ValidationException::withMessages([
                        'cod' => 'Cannot mark as cash collected: recorded collection amount is zero or negative.',
                    ]);
                }
            }

            // 2. Handle stock restoration on cancellation, refusal, or return
            if ($newStatus === self::STATUS_CANCELLED) {
                if (empty($reason)) {
                    throw ValidationException::withMessages([
                        'reason' => 'A cancellation reason is required.',
                    ]);
                }
                $this->stockService->restoreOrderStock(
                    $order,
                    $reason,
                    InventoryMovement::TYPE_CANCELLATION_RESTOCK,
                    $user
                );
            } elseif ($newStatus === self::STATUS_REFUSED) {
                if (empty($reason)) {
                    throw ValidationException::withMessages([
                        'reason' => 'A refusal reason is required.',
                    ]);
                }
                $this->stockService->restoreOrderStock(
                    $order,
                    $reason,
                    InventoryMovement::TYPE_CANCELLATION_RESTOCK,
                    $user
                );
                $order->delivery()->update([
                    'status' => 'returned',
                    'notes' => 'Customer refused delivery: ' . $reason,
                ]);
            } elseif ($newStatus === self::STATUS_RETURNED) {
                if (empty($reason)) {
                    throw ValidationException::withMessages([
                        'reason' => 'A return reason is required.',
                    ]);
                }
                $this->stockService->restoreOrderStock(
                    $order,
                    $reason,
                    InventoryMovement::TYPE_CUSTOMER_RETURN,
                    $user
                );
                $order->delivery()->update([
                    'status' => 'returned',
                    'notes' => 'Customer returned package: ' . $reason,
                ]);
            }

            // 3. Handle commission reversals on cancellation, refusal, or return
            if (in_array($newStatus, [self::STATUS_CANCELLED, self::STATUS_REFUSED, self::STATUS_RETURNED], true)) {
                app(CommissionService::class)->reverseOrderCommissions($order, "Order status changed to {$newStatus}: {$reason}");
            }

            // 4. Update Order Status
            $order->status = $newStatus;
            $order->save();

            // 5. Audit Log
            AuditLog::log('order_status_changed', $order, [
                'status' => $currentStatus,
            ], [
                'status' => $newStatus,
                'reason' => $reason,
                'user_id' => $user?->id,
            ]);

            // 6. Dispatch Customer Order Lifecycle Email (afterCommit safe)
            $freshOrder = $order->fresh(['items.product', 'delivery', 'codCollection']) ?? $order;
            $emailService = app(CustomerEmailNotificationService::class);

            match ($newStatus) {
                self::STATUS_CONFIRMED => $emailService->sendOrderConfirmedNotification($freshOrder),
                self::STATUS_SHIPPED => $emailService->sendOrderShippedNotification($freshOrder),
                self::STATUS_DELIVERED => $emailService->sendOrderDeliveredNotification($freshOrder),
                self::STATUS_COMPLETED => $emailService->sendOrderCompletedNotification($freshOrder),
                self::STATUS_CANCELLED => $emailService->sendOrderCancelledNotification($freshOrder, $reason),
                self::STATUS_REFUSED => $emailService->sendOrderRefusedNotification($freshOrder, $reason),
                self::STATUS_RETURNED => $emailService->sendOrderReturnedNotification($freshOrder, $reason),
                default => null, // Includes STATUS_PACKED (internal fulfillment staging event: strictly zero email)
            };

            return $freshOrder;
        });
    }

    /**
     * Record COD Cash Collection and reconcile against expected amount.
     */
    public function recordCodCollection(
        Order $order,
        float $collectedAmount,
        ?string $courierReference = null,
        ?string $courierName = null,
        ?string $notes = null,
        ?User $user = null
    ): CodCollection {
        return DB::transaction(function () use ($order, $collectedAmount, $courierReference, $courierName, $notes, $user) {
            $expectedAmount = (float) $order->total_amount;
            $user = $user ?? auth()->user();

            // Determine reconciliation status
            if (abs($collectedAmount - $expectedAmount) < 0.01) {
                $status = CodCollection::STATUS_MATCHED;
            } elseif ($collectedAmount < $expectedAmount) {
                $status = CodCollection::STATUS_MISMATCHED_UNDERPAID;
            } else {
                $status = CodCollection::STATUS_MISMATCHED_OVERPAID;
            }

            $cod = CodCollection::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'expected_amount' => $expectedAmount,
                    'collected_amount' => $collectedAmount,
                    'courier_name' => $courierName ?? $order->delivery?->courier_name,
                    'courier_reference' => $courierReference,
                    'reconciled_by' => $user?->id,
                    'reconciliation_status' => $status,
                    'collected_at' => now(),
                    'notes' => $notes,
                ]
            );

            AuditLog::log('cod_reconciled', $order, null, [
                'expected' => $expectedAmount,
                'collected' => $collectedAmount,
                'reconciliation_status' => $status,
                'reconciled_by' => $user?->id,
            ]);

            return $cod;
        });
    }
}

