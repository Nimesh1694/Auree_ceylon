<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CodCollection;
use App\Models\Delivery;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Create an order from validated checkout data and verified cart items.
     * All prices, totals, and stock are strictly validated on the server.
     */
    public function createOrder(
        array $customerData,
        array $rawCart,
        ?User $authenticatedUser = null,
        ?string $referralCode = null,
        ?int $agentId = null,
        ?string $couponCode = null
    ): Order {
        if (empty($rawCart)) {
            throw ValidationException::withMessages([
                'cart' => 'Your shopping cart is empty.',
            ]);
        }

        return DB::transaction(function () use ($customerData, $rawCart, $authenticatedUser, $referralCode, $agentId, $couponCode) {
            $productIds = array_keys($rawCart);

            // Row-locking on products to prevent race conditions & double-selling
            $products = Product::whereIn('id', $productIds)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $orderItemsData = [];
            $subtotal = 0.00;

            foreach ($rawCart as $productId => $qty) {
                $product = $products->get($productId);

                if (!$product) {
                    throw ValidationException::withMessages([
                        'cart' => "A product in your cart is no longer available.",
                    ]);
                }

                if ($product->stock_quantity < $qty) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for '{$product->name_en}'. Only {$product->stock_quantity} available.",
                    ]);
                }

                $unitPrice = (float) $product->price;
                $lineSubtotal = round($unitPrice * $qty, 2);
                $subtotal += $lineSubtotal;

                $orderItemsData[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $deliveryCharge = (float) Setting::get('default_delivery_charge', 350.00);
            $normalizedPhone = self::normalizePhoneNumber($customerData['customer_phone']);

            // Validate and calculate coupon discount if provided
            $couponResult = null;
            $couponDiscountAmount = 0.00;
            $shippingDiscountAmount = 0.00;

            if (!empty($couponCode)) {
                $cartItemsForCoupon = [];
                foreach ($orderItemsData as $idx => $itemData) {
                    $cartItemsForCoupon[] = [
                        'id' => $idx + 1,
                        'product_id' => $itemData['product']->id,
                        'category_id' => $itemData['product']->category_id,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                    ];
                }

                $couponService = app(CouponService::class);
                $couponResult = $couponService->validateAndCalculate(
                    couponOrCode: $couponCode,
                    cartItems: $cartItemsForCoupon,
                    customer: $authenticatedUser,
                    customerEmail: $customerData['customer_email'] ?? null,
                    customerPhone: $normalizedPhone,
                    deliveryCharge: $deliveryCharge,
                    referralCode: $referralCode
                );

                $couponDiscountAmount = (float) $couponResult['discount_amount'];
                $shippingDiscountAmount = (float) $couponResult['shipping_discount_amount'];
            }

            $finalDeliveryCharge = max(0.00, round($deliveryCharge - $shippingDiscountAmount, 2));
            $totalAmount = max(0.00, round($subtotal - $couponDiscountAmount + $finalDeliveryCharge, 2));

            // Create Order
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $authenticatedUser?->id,
                'customer_name' => $customerData['customer_name'],
                'customer_phone' => $normalizedPhone,
                'customer_email' => $customerData['customer_email'] ?? null,
                'shipping_address' => $customerData['shipping_address'],
                'district' => $customerData['district'],
                'city' => $customerData['city'],
                'postal_code' => $customerData['postal_code'] ?? null,
                'coupon_id' => $couponResult ? $couponResult['coupon']->id : null,
                'coupon_code' => $couponResult ? $couponResult['coupon']->code : null,
                'subtotal' => $subtotal,
                'discount_amount' => $couponDiscountAmount,
                'delivery_charge' => $deliveryCharge,
                'shipping_discount_amount' => $shippingDiscountAmount,
                'total_amount' => $totalAmount,
                'payment_method' => 'cod',
                'status' => 'placed',
                'agent_id' => $agentId,
                'referral_code' => $referralCode,
                'tracking_token' => Order::generateTrackingToken(),
                'notes' => $customerData['notes'] ?? null,
                'placed_at' => now(),
            ]);

            // Create OrderItems and safely deduct stock with inventory movement ledger
            $couponService = app(CouponService::class);
            foreach ($orderItemsData as $idx => $itemData) {
                /** @var Product $product */
                $product = $itemData['product'];
                $qty = $itemData['quantity'];
                $previousStock = $product->stock_quantity;
                $newStock = $previousStock - $qty;

                $lineAlloc = $couponResult['line_allocations'][$idx] ?? null;
                $itemDiscountAmount = $lineAlloc ? (float) $lineAlloc['allocated_amount'] : 0.00;
                $itemAllocCents = $lineAlloc ? (int) $lineAlloc['allocated_cents'] : 0;
                $lineSubtotalCents = intval(bcmul((string) $itemData['unit_price'], '100', 0)) * $qty;
                $unitSchedule = $couponService->computeUnitRefundSchedule($lineSubtotalCents, $itemAllocCents, $qty);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name_en, // Immutable snapshot
                    'product_sku' => $product->sku,       // Immutable snapshot
                    'unit_price' => $itemData['unit_price'],
                    'cost_price' => $product->cost_price !== null ? (float) $product->cost_price : null, // Immutable historical cost snapshot
                    'quantity' => $qty,
                    'subtotal' => $itemData['line_subtotal'],
                    'discount_amount' => $itemDiscountAmount,
                    'unit_refund_schedule' => $unitSchedule,
                    'refunded_unit_indexes' => [],
                    'returned_quantity' => 0,
                    'refunded_amount' => 0.00,
                ]);

                // Safely decrement inventory
                $product->decrement('stock_quantity', $qty);

                // Record inventory movement ledger
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => InventoryMovement::TYPE_ORDER_DEDUCTION,
                    'quantity' => -$qty,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reason' => "Customer Order #{$order->order_number}",
                    'order_id' => $order->id,
                    'user_id' => $authenticatedUser?->id,
                ]);

                // Sync automatic low-stock alert lifecycle after stock deduction
                $product->refresh();
                app(LowStockAlertService::class)->syncForProduct($product, InventoryMovement::TYPE_ORDER_DEDUCTION);
            }

            // Record coupon usage and atomically increment usage count under lock
            if ($couponResult) {
                $lockedCoupon = \App\Models\Coupon::where('id', $couponResult['coupon']->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedCoupon->usage_limit_total !== null && $lockedCoupon->times_used >= $lockedCoupon->usage_limit_total) {
                    throw ValidationException::withMessages([
                        'coupon' => 'This coupon reached its maximum usage limit while processing.',
                    ]);
                }

                $lockedCoupon->increment('times_used');

                \App\Models\CouponUsage::create([
                    'coupon_id' => $lockedCoupon->id,
                    'order_id' => $order->id,
                    'customer_id' => $authenticatedUser?->id,
                    'customer_email' => $order->customer_email ?? ($authenticatedUser?->email ?? 'guest@example.com'),
                    'customer_phone' => $order->customer_phone,
                    'discount_amount' => $couponDiscountAmount,
                    'shipping_discount_amount' => $shippingDiscountAmount,
                    'created_at' => now(),
                ]);
            }

            // Create Delivery record
            Delivery::create([
                'order_id' => $order->id,
                'status' => 'pending',
            ]);

            // Create initial pending COD Collection record
            CodCollection::create([
                'order_id' => $order->id,
                'expected_amount' => $totalAmount,
                'collected_amount' => 0.00,
                'reconciliation_status' => CodCollection::STATUS_PENDING,
            ]);

            // Audit log
            AuditLog::log('order_placed', $order, null, [
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'customer_phone' => $order->customer_phone,
            ]);

            // Calculate & record immutable pending commissions if attributed
            app(CommissionService::class)->calculateAndRecordPendingCommissions($order);

            // Dispatch customer order placed email notification (afterCommit safe)
            app(CustomerEmailNotificationService::class)->sendOrderPlacedNotification($order);

            return $order;
        });
    }

    /**
     * Normalize Sri Lankan phone numbers safely before persistence.
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($digits, '94') && strlen($digits) === 11) {
            return '0' . substr($digits, 2);
        }
        if (strlen($digits) === 9 && in_array($digits[0], ['7', '1', '2', '3', '4', '5', '6', '8'])) {
            return '0' . $digits;
        }
        return $digits;
    }

    /**
     * Securely track an order by order number and customer phone number.
     * Prevents sequential ID guessing attacks.
     */
    public function trackOrder(string $orderNumber, string $phone): ?Order
    {
        $orderNumber = trim($orderNumber);
        $phone = trim($phone);

        // Normalize phone number for consistent matching (strip spaces, dashes)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        return Order::with(['items.product', 'delivery'])
            ->where('order_number', $orderNumber)
            ->where(function ($query) use ($cleanPhone, $phone) {
                $query->where('customer_phone', $phone)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(customer_phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$cleanPhone}%"]);
            })
            ->first();
    }
}

