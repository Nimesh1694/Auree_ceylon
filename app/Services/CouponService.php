<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validate coupon eligibility and calculate exact discount amounts & line allocations.
     * Uses strict integer cents and BCMath.
     *
     * @param  Coupon|string  $couponOrCode
     * @param  array  $cartItems  Array of items with 'product_id', 'quantity', 'unit_price', 'category_id'
     * @param  User|null  $customer
     * @param  string|null  $customerEmail
     * @param  string|null  $customerPhone
     * @param  float  $deliveryCharge
     * @param  string|null  $referralCode
     * @return array
     *
     * @throws ValidationException
     */
    public function validateAndCalculate(
        Coupon|string $couponOrCode,
        array $cartItems,
        ?User $customer = null,
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        float $deliveryCharge = 350.00,
        ?string $referralCode = null
    ): array {
        $coupon = is_string($couponOrCode)
            ? Coupon::with(['categories', 'products'])->where('code', trim($couponOrCode))->first()
            : $couponOrCode;

        if (!$coupon) {
            throw ValidationException::withMessages([
                'coupon' => 'The coupon code entered does not exist.',
            ]);
        }

        // 1. Active & Validity Window Checks
        if (!$coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon is currently inactive.',
            ]);
        }

        $now = Carbon::now();
        if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon is not yet valid.',
            ]);
        }

        if ($coupon->expires_at && $now->gt($coupon->expires_at)) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon has expired.',
            ]);
        }

        // 2. Referral Code Stacking Rule
        if (!empty($referralCode) && !$coupon->allow_agent_referral) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon cannot be combined with an agent referral code.',
            ]);
        }

        // 3. Global Usage Limit Check
        if ($coupon->usage_limit_total !== null && $coupon->times_used >= $coupon->usage_limit_total) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon has reached its maximum global usage limit.',
            ]);
        }

        // 4. Per-Customer Usage Limit Check
        if ($coupon->usage_limit_per_customer !== null) {
            $userUsageQuery = CouponUsage::where('coupon_id', $coupon->id)
                ->whereNull('restored_at');

            $alreadyUsedCount = 0;
            if ($customer) {
                $alreadyUsedCount = (clone $userUsageQuery)
                    ->where('customer_id', $customer->id)
                    ->count();
            }

            if ($alreadyUsedCount === 0 && !empty($customerEmail)) {
                $alreadyUsedCount = (clone $userUsageQuery)
                    ->where('customer_email', trim(strtolower($customerEmail)))
                    ->count();
            }

            if ($alreadyUsedCount === 0 && !empty($customerPhone)) {
                $cleanPhone = OrderService::normalizePhoneNumber($customerPhone);
                $alreadyUsedCount = (clone $userUsageQuery)
                    ->where('customer_phone', $cleanPhone)
                    ->count();
            }

            if ($alreadyUsedCount >= $coupon->usage_limit_per_customer) {
                throw ValidationException::withMessages([
                    'coupon' => 'You have already reached the maximum usage limit for this coupon.',
                ]);
            }
        }

        // 5. Eligible Items & Minimum Subtotal Evaluation
        if (empty($cartItems)) {
            throw ValidationException::withMessages([
                'cart' => 'Your shopping cart is empty.',
            ]);
        }

        $couponCategoryIds = $coupon->relationLoaded('categories')
            ? $coupon->categories->pluck('id')->all()
            : $coupon->categories()->pluck('categories.id')->all();

        $couponProductIds = $coupon->relationLoaded('products')
            ? $coupon->products->pluck('id')->all()
            : $coupon->products()->pluck('products.id')->all();

        $eligibleItems = [];
        $eligibleSubtotalCents = 0;
        $totalCartSubtotalCents = 0;

        foreach ($cartItems as $index => $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $unitPrice = (string) ($item['unit_price'] ?? '0.00');
            $productId = (int) ($item['product_id'] ?? 0);
            $categoryId = isset($item['category_id']) ? (int) $item['category_id'] : null;

            $unitPriceCents = intval(bcmul($unitPrice, '100', 0));
            $lineSubtotalCents = $unitPriceCents * $qty;
            $totalCartSubtotalCents += $lineSubtotalCents;

            $isEligible = match ($coupon->applies_to) {
                Coupon::APPLIES_CATEGORIES => $categoryId && in_array($categoryId, $couponCategoryIds, true),
                Coupon::APPLIES_PRODUCTS => in_array($productId, $couponProductIds, true),
                default => true,
            };

            if ($isEligible) {
                $eligibleItems[] = [
                    'index' => $index,
                    'item_id' => $item['id'] ?? ($index + 1),
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price_cents' => $unitPriceCents,
                    'line_subtotal_cents' => $lineSubtotalCents,
                ];
                $eligibleSubtotalCents += $lineSubtotalCents;
            }
        }

        if (empty($eligibleItems) || $eligibleSubtotalCents <= 0) {
            throw ValidationException::withMessages([
                'coupon' => 'None of the items in your cart are eligible for this coupon.',
            ]);
        }

        // Minimum order subtotal is evaluated strictly against ELIGIBLE products subtotal
        if ($coupon->min_order_subtotal !== null && bccomp((string)$coupon->min_order_subtotal, '0.00', 2) > 0) {
            $minSubtotalCents = intval(bcmul((string)$coupon->min_order_subtotal, '100', 0));
            if ($eligibleSubtotalCents < $minSubtotalCents) {
                $formattedMin = number_format((float) $coupon->min_order_subtotal, 2);
                throw ValidationException::withMessages([
                    'coupon' => "This coupon requires a minimum eligible subtotal of LKR {$formattedMin}.",
                ]);
            }
        }

        // 6. Calculate Order-Level Discount Cents
        $orderDiscountCents = 0;
        $shippingDiscountCents = 0;
        $deliveryChargeCents = intval(bcmul((string)$deliveryCharge, '100', 0));

        if ($coupon->isFreeShipping()) {
            $shippingDiscountCents = $deliveryChargeCents;
            $orderDiscountCents = 0;
        } elseif ($coupon->isFixedAmount()) {
            $desiredCents = intval(bcmul((string)$coupon->value, '100', 0));
            $orderDiscountCents = min($desiredCents, $eligibleSubtotalCents);
        } elseif ($coupon->isPercentage()) {
            // Canonical Integer-Cents Percentage Formula (Section 3.1):
            // D_raw = floor((S_cents * P_scaled + 5000) / 10000)
            $pScaled = intval(bcmul((string)$coupon->value, '100', 0));
            $rawDiscountCents = intdiv($eligibleSubtotalCents * $pScaled + 5000, 10000);

            // Effective Cap in Cents (Section 3.2):
            $effectiveCapCents = $coupon->getEffectiveCapCents();

            if ($effectiveCapCents !== null) {
                $orderDiscountCents = min($rawDiscountCents, $effectiveCapCents, $eligibleSubtotalCents);
            } else {
                $orderDiscountCents = min($rawDiscountCents, $eligibleSubtotalCents);
            }
        }

        // 7. Hare-Niemeyer Largest Remainder Allocation across eligible items (Section 4.1)
        $lineAllocations = [];
        if ($orderDiscountCents > 0 && $eligibleSubtotalCents > 0) {
            $exactAllocations = [];
            $baseCentsSum = 0;

            foreach ($eligibleItems as $elItem) {
                // ExactAlloc = (D_order_cents * S_i_cents) / S_cents
                $numerator = $orderDiscountCents * $elItem['line_subtotal_cents'];
                $baseCents = intdiv($numerator, $eligibleSubtotalCents);
                $remainder = $numerator % $eligibleSubtotalCents;

                $baseCentsSum += $baseCents;
                $exactAllocations[] = [
                    'index' => $elItem['index'],
                    'item_id' => $elItem['item_id'],
                    'base_cents' => $baseCents,
                    'remainder' => $remainder,
                    'line_subtotal_cents' => $elItem['line_subtotal_cents'],
                    'quantity' => $elItem['quantity'],
                ];
            }

            // Leftover cents to distribute
            $leftoverCents = $orderDiscountCents - $baseCentsSum;

            // Deterministic Tie-Breaker (FND-15-07A):
            // Sort primary by remainder DESC, secondary by item_id ASC
            usort($exactAllocations, function ($a, $b) {
                if ($a['remainder'] !== $b['remainder']) {
                    return $b['remainder'] <=> $a['remainder']; // Descending remainder
                }
                return $a['item_id'] <=> $b['item_id']; // Ascending ID tie-breaker
            });

            // Distribute 1 cent to top R items
            for ($k = 0; $k < count($exactAllocations); $k++) {
                $allocCents = $exactAllocations[$k]['base_cents'] + ($k < $leftoverCents ? 1 : 0);
                $origIndex = $exactAllocations[$k]['index'];
                $qty = $exactAllocations[$k]['quantity'];
                $lineSubtotalCents = $exactAllocations[$k]['line_subtotal_cents'];

                // Unit refund schedule calculation (Section 4.2 / FND-15-07B)
                $unitSchedule = $this->computeUnitRefundSchedule($lineSubtotalCents, $allocCents, $qty);

                $lineAllocations[$origIndex] = [
                    'allocated_cents' => $allocCents,
                    'allocated_amount' => bcdiv((string)$allocCents, '100', 2),
                    'unit_refund_schedule' => $unitSchedule,
                ];
            }
        }

        $discountAmount = bcdiv((string)$orderDiscountCents, '100', 2);
        $shippingDiscountAmount = bcdiv((string)$shippingDiscountCents, '100', 2);

        return [
            'coupon' => $coupon,
            'discount_cents' => $orderDiscountCents,
            'discount_amount' => (float)$discountAmount,
            'shipping_discount_cents' => $shippingDiscountCents,
            'shipping_discount_amount' => (float)$shippingDiscountAmount,
            'line_allocations' => $lineAllocations,
            'eligible_subtotal_cents' => $eligibleSubtotalCents,
            'total_cart_subtotal_cents' => $totalCartSubtotalCents,
        ];
    }

    /**
     * Compute authoritative integer-cent unit refund schedule (Section 4.2).
     */
    public function computeUnitRefundSchedule(int $lineSubtotalCents, int $allocatedDiscountCents, int $quantity): array
    {
        $netLineBaseCents = $lineSubtotalCents - $allocatedDiscountCents;
        $baseUnitCents = intdiv($netLineBaseCents, $quantity);
        $remUnit = $netLineBaseCents % $quantity;

        $schedule = [];
        for ($u = 1; $u <= $quantity; $u++) {
            $unitCents = $baseUnitCents + ($u <= $remUnit ? 1 : 0);
            $schedule[] = [
                'unit_index' => $u,
                'net_cents' => $unitCents,
                'display_amount' => bcdiv((string)$unitCents, '100', 2),
            ];
        }

        return $schedule;
    }

    /**
     * Atomically apply coupon to an order, lock row, record usage, and save snapshots.
     */
    public function applyCouponToOrder(
        Order $order,
        Coupon $coupon,
        array $calculated,
        ?User $customer = null
    ): CouponUsage {
        return DB::transaction(function () use ($order, $coupon, $calculated, $customer) {
            // Pessimistic row-locking to prevent concurrent quota bypass
            $lockedCoupon = Coupon::where('id', $coupon->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCoupon->usage_limit_total !== null && $lockedCoupon->times_used >= $lockedCoupon->usage_limit_total) {
                throw ValidationException::withMessages([
                    'coupon' => 'This coupon reached its maximum usage limit while processing.',
                ]);
            }

            // Per-customer limit check under lock
            if ($lockedCoupon->usage_limit_per_customer !== null) {
                $userUsageQuery = CouponUsage::where('coupon_id', $lockedCoupon->id)
                    ->whereNull('restored_at');

                $alreadyUsedCount = 0;
                if ($customer) {
                    $alreadyUsedCount = (clone $userUsageQuery)
                        ->where('customer_id', $customer->id)
                        ->count();
                }

                if ($alreadyUsedCount === 0 && !empty($order->customer_email)) {
                    $alreadyUsedCount = (clone $userUsageQuery)
                        ->where('customer_email', trim(strtolower($order->customer_email)))
                        ->count();
                }

                if ($alreadyUsedCount === 0 && !empty($order->customer_phone)) {
                    $cleanPhone = OrderService::normalizePhoneNumber($order->customer_phone);
                    $alreadyUsedCount = (clone $userUsageQuery)
                        ->where('customer_phone', $cleanPhone)
                        ->count();
                }

                if ($alreadyUsedCount >= $lockedCoupon->usage_limit_per_customer) {
                    throw ValidationException::withMessages([
                        'coupon' => 'You have already reached the maximum usage limit for this coupon.',
                    ]);
                }
            }

            // Increment usage
            $lockedCoupon->increment('times_used');

            // Record immutable usage audit record
            $usage = CouponUsage::create([
                'coupon_id' => $lockedCoupon->id,
                'order_id' => $order->id,
                'customer_id' => $customer?->id ?? $order->customer_id,
                'customer_email' => $order->customer_email ?? ($customer?->email ?? 'guest@example.com'),
                'customer_phone' => OrderService::normalizePhoneNumber($order->customer_phone),
                'discount_amount' => $calculated['discount_amount'],
                'shipping_discount_amount' => $calculated['shipping_discount_amount'],
                'created_at' => now(),
            ]);

            // Update order snapshots and recalculate total
            $subtotal = (float)$order->subtotal;
            $deliveryCharge = (float)$order->delivery_charge;
            $discountAmount = (float)$calculated['discount_amount'];
            $shippingDiscountAmount = (float)$calculated['shipping_discount_amount'];
            $netTotal = round(max(0, $subtotal - $discountAmount + max(0, $deliveryCharge - $shippingDiscountAmount)), 2);

            $order->update([
                'coupon_id' => $lockedCoupon->id,
                'coupon_code' => $lockedCoupon->code,
                'discount_amount' => $discountAmount,
                'shipping_discount_amount' => $shippingDiscountAmount,
                'total_amount' => $netTotal,
            ]);

            // Save line-item snapshots and unit refund schedules
            $lineAllocations = $calculated['line_allocations'] ?? [];
            $orderItems = $order->items;
            foreach ($orderItems as $idx => $orderItem) {
                if (isset($lineAllocations[$idx])) {
                    $alloc = $lineAllocations[$idx];
                    $orderItem->update([
                        'discount_amount' => $alloc['allocated_amount'],
                        'unit_refund_schedule' => $alloc['unit_refund_schedule'],
                        'refunded_unit_indexes' => [],
                        'returned_quantity' => 0,
                        'refunded_amount' => 0.00,
                    ]);
                } else {
                    // Item received zero discount: still build its default unit refund schedule
                    $unitPriceCents = intval(bcmul((string)$orderItem->unit_price, '100', 0));
                    $lineSubtotalCents = $unitPriceCents * $orderItem->quantity;
                    $unitSchedule = $this->computeUnitRefundSchedule($lineSubtotalCents, 0, $orderItem->quantity);

                    $orderItem->update([
                        'discount_amount' => 0.00,
                        'unit_refund_schedule' => $unitSchedule,
                        'refunded_unit_indexes' => [],
                        'returned_quantity' => 0,
                        'refunded_amount' => 0.00,
                    ]);
                }
            }

            return $usage;
        });
    }

    /**
     * Restore coupon usage count when an order is cancelled or rolled back.
     * Idempotent: will not restore more than once.
     */
    public function restoreCouponUsage(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $usage = CouponUsage::where('order_id', $order->id)
                ->whereNull('restored_at')
                ->lockForUpdate()
                ->first();

            if (!$usage) {
                return false;
            }

            $usage->update(['restored_at' => now()]);

            $coupon = Coupon::where('id', $usage->coupon_id)
                ->lockForUpdate()
                ->first();

            if ($coupon && $coupon->times_used > 0) {
                $coupon->decrement('times_used');
            }

            AuditLog::log('coupon_usage_restored', $order, null, [
                'order_number' => $order->order_number,
                'coupon_id' => $usage->coupon_id,
                'usage_id' => $usage->id,
            ]);

            return true;
        });
    }

    /**
     * Create a new coupon enforcing Owner approval for high/unlimited caps.
     */
    public function createCoupon(array $data, User $actor): Coupon
    {
        // 1. RBAC authorization
        if ($actor->isSuperAdmin()) {
            throw new AuthorizationException('Super Administrators are forbidden from creating commercial marketing coupons.');
        }

        if (!$actor->hasPermissionTo('coupons.create')) {
            throw new AuthorizationException('You do not have permission to create coupons.');
        }

        // 2. High/Unlimited Cap Commercial Protection (Owner Only)
        $isUnlimited = !empty($data['is_unlimited_cap']);
        $maxDiscount = isset($data['max_discount_amount']) && $data['max_discount_amount'] !== null
            ? (float)$data['max_discount_amount']
            : null;

        if (($isUnlimited || ($maxDiscount !== null && $maxDiscount > 5000.00)) && !$actor->isOwner()) {
            throw new AuthorizationException('Only the Business Owner can authorize an unlimited discount cap or a cap exceeding LKR 5,000.00.');
        }

        return DB::transaction(function () use ($data, $actor) {
            $coupon = Coupon::create([
                'code' => strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
                'type' => $data['type'],
                'value' => (float)($data['value'] ?? 0.00),
                'min_order_subtotal' => (float)($data['min_order_subtotal'] ?? 0.00),
                'max_discount_amount' => $data['max_discount_amount'] ?? null,
                'is_unlimited_cap' => !empty($data['is_unlimited_cap']),
                'usage_limit_total' => $data['usage_limit_total'] ?? null,
                'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
                'times_used' => 0,
                'is_active' => $data['is_active'] ?? true,
                'is_public' => $data['is_public'] ?? true,
                'applies_to' => $data['applies_to'] ?? Coupon::APPLIES_ALL,
                'allow_agent_referral' => $data['allow_agent_referral'] ?? true,
                'starts_at' => $data['starts_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($coupon->applies_to === Coupon::APPLIES_CATEGORIES && !empty($data['category_ids'])) {
                $coupon->categories()->sync($data['category_ids']);
            } elseif ($coupon->applies_to === Coupon::APPLIES_PRODUCTS && !empty($data['product_ids'])) {
                $coupon->products()->sync($data['product_ids']);
            }

            AuditLog::log('coupon_created', $coupon, null, [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
            ]);

            return $coupon;
        });
    }

    /**
     * Update an existing coupon enforcing Owner rules for cap adjustments.
     */
    public function updateCoupon(Coupon $coupon, array $data, User $actor): Coupon
    {
        if ($actor->isSuperAdmin()) {
            throw new AuthorizationException('Super Administrators are forbidden from updating commercial marketing coupons.');
        }

        if (!$actor->hasPermissionTo('coupons.update')) {
            throw new AuthorizationException('You do not have permission to update coupons.');
        }

        $isUnlimited = array_key_exists('is_unlimited_cap', $data)
            ? (bool)$data['is_unlimited_cap']
            : $coupon->is_unlimited_cap;

        $maxDiscount = array_key_exists('max_discount_amount', $data)
            ? ($data['max_discount_amount'] !== null ? (float)$data['max_discount_amount'] : null)
            : ($coupon->max_discount_amount !== null ? (float)$coupon->max_discount_amount : null);

        if (($isUnlimited || ($maxDiscount !== null && $maxDiscount > 5000.00)) && !$actor->isOwner()) {
            throw new AuthorizationException('Only the Business Owner can authorize an unlimited discount cap or a cap exceeding LKR 5,000.00.');
        }

        return DB::transaction(function () use ($coupon, $data, $actor) {
            $coupon->update([
                'name' => isset($data['name']) ? trim($data['name']) : $coupon->name,
                'value' => isset($data['value']) ? (float)$data['value'] : $coupon->value,
                'min_order_subtotal' => isset($data['min_order_subtotal']) ? (float)$data['min_order_subtotal'] : $coupon->min_order_subtotal,
                'max_discount_amount' => array_key_exists('max_discount_amount', $data) ? $data['max_discount_amount'] : $coupon->max_discount_amount,
                'is_unlimited_cap' => array_key_exists('is_unlimited_cap', $data) ? (bool)$data['is_unlimited_cap'] : $coupon->is_unlimited_cap,
                'usage_limit_total' => array_key_exists('usage_limit_total', $data) ? $data['usage_limit_total'] : $coupon->usage_limit_total,
                'usage_limit_per_customer' => array_key_exists('usage_limit_per_customer', $data) ? $data['usage_limit_per_customer'] : $coupon->usage_limit_per_customer,
                'is_active' => array_key_exists('is_active', $data) ? (bool)$data['is_active'] : $coupon->is_active,
                'is_public' => array_key_exists('is_public', $data) ? (bool)$data['is_public'] : $coupon->is_public,
                'applies_to' => $data['applies_to'] ?? $coupon->applies_to,
                'allow_agent_referral' => array_key_exists('allow_agent_referral', $data) ? (bool)$data['allow_agent_referral'] : $coupon->allow_agent_referral,
                'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $coupon->starts_at,
                'expires_at' => array_key_exists('expires_at', $data) ? $data['expires_at'] : $coupon->expires_at,
            ]);

            if ($coupon->applies_to === Coupon::APPLIES_CATEGORIES && isset($data['category_ids'])) {
                $coupon->categories()->sync($data['category_ids']);
            } elseif ($coupon->applies_to === Coupon::APPLIES_PRODUCTS && isset($data['product_ids'])) {
                $coupon->products()->sync($data['product_ids']);
            }

            AuditLog::log('coupon_updated', $coupon, null, [
                'code' => $coupon->code,
                'is_active' => $coupon->is_active,
            ]);

            return $coupon;
        });
    }

    /**
     * Toggle coupon active state.
     */
    public function toggleActive(Coupon $coupon, User $actor): bool
    {
        if ($actor->isSuperAdmin()) {
            throw new AuthorizationException('Super Administrators are forbidden from toggling commercial coupon activation status.');
        }

        if (!$actor->hasPermissionTo('coupons.toggle_active')) {
            throw new AuthorizationException('You do not have permission to toggle coupon activation status.');
        }

        $coupon->is_active = !$coupon->is_active;
        $coupon->save();

        AuditLog::log('coupon_status_toggled', $coupon, null, [
            'code' => $coupon->code,
            'is_active' => $coupon->is_active,
        ]);

        return $coupon->is_active;
    }

    /**
     * Permanently delete a coupon (Owner only; prohibited if historical usages exist).
     */
    public function deleteCoupon(Coupon $coupon, User $actor): bool
    {
        if (!$actor->isOwner()) {
            throw new AuthorizationException('Only the Business Owner is authorized to permanently delete marketing coupons.');
        }

        if ($coupon->usages()->exists()) {
            throw new \RuntimeException('Cannot delete coupon: historical financial audit usage records exist. Deactivate the coupon instead.');
        }

        AuditLog::log('coupon_deleted', $coupon, null, [
            'code' => $coupon->code,
        ]);

        return (bool) $coupon->delete();
    }
}
