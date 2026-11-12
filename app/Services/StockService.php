<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
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
        ?User $user = null
    ): InventoryMovement {
        if ($quantityDelta === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity change cannot be zero.',
            ]);
        }

        return DB::transaction(function () use ($product, $quantityDelta, $movementType, $reason, $order, $user) {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

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
            ]);

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
            }

            return $movements;
        });
    }
}

