<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\CouponService;
use App\Services\OrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CouponSystemTest extends TestCase
{
    use RefreshDatabase;

    protected CouponService $couponService;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponService = app(CouponService::class);
        $this->orderService = app(OrderService::class);

        // Seed roles and permissions for authorization testing
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function createOwnerUser(): User
    {
        return User::factory()->create([
            'role' => UserRole::OWNER->value,
            'email' => 'owner@auraluxe.com',
        ]);
    }

    protected function createSuperAdminUser(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'email' => 'superadmin@auraluxe.com',
        ]);
    }

    protected function createMarketingUser(): User
    {
        return User::factory()->create([
            'role' => UserRole::MARKETING_MANAGER->value,
            'email' => 'marketing@auraluxe.com',
        ]);
    }

    protected function createCustomerUser(string $email = 'customer@example.com'): User
    {
        return User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'email' => $email,
            'phone' => '0771234567',
        ]);
    }

    protected function createTestProduct(float $price = 1000.00, ?int $categoryId = null): Product
    {
        return Product::create([
            'name_en' => 'Luxury Face Serum',
            'name_si' => 'සුඛෝපභෝගී සීරම්',
            'slug' => 'luxury-face-serum-' . uniqid(),
            'sku' => 'SKU-' . strtoupper(uniqid()),
            'price' => $price,
            'cost_price' => round($price * 0.4, 2),
            'stock_quantity' => 100,
            'is_active' => true,
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Test 1.01 [Global 01]: Percentage discount calculates with exact half-up rounding cents.
     */
    public function test_percentage_discount_calculates_with_exact_half_up_rounding_cents(): void
    {
        $coupon = Coupon::create([
            'code' => 'PCT15',
            'name' => '15% Off',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 15.00,
            'is_active' => true,
        ]);

        $cartItems = [
            ['id' => 1, 'product_id' => 101, 'quantity' => 1, 'unit_price' => '100.00'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        // 100.00 * 15% = 15.00 (1500 cents)
        $this->assertEquals(1500, $res['discount_cents']);
        $this->assertEquals(15.00, $res['discount_amount']);
    }

    /**
     * Test 1.02 [Global 02]: Percentage boundary: 1 cent * 50% = 1 cent.
     * Canonical formula: floor((1 * 5000 + 5000) / 10000) = floor(10000 / 10000) = 1 cent.
     */
    public function test_percentage_discount_boundary_1_cent_times_50_percent_equals_1_cent(): void
    {
        $coupon = Coupon::create([
            'code' => 'HALF50',
            'name' => '50% Off',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 50.00,
            'is_active' => true,
        ]);

        $cartItems = [
            ['id' => 1, 'product_id' => 101, 'quantity' => 1, 'unit_price' => '0.01'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        $this->assertEquals(1, $res['discount_cents']);
        $this->assertEquals(0.01, $res['discount_amount']);
    }

    /**
     * Test 1.03 [Global 03]: Percentage boundary: 1 cent * 49% = 0 cents.
     * Canonical formula: floor((1 * 4900 + 5000) / 10000) = floor(9900 / 10000) = 0 cents.
     */
    public function test_percentage_discount_boundary_1_cent_times_49_percent_equals_0_cents(): void
    {
        $coupon = Coupon::create([
            'code' => 'PCT49',
            'name' => '49% Off',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 49.00,
            'is_active' => true,
        ]);

        $cartItems = [
            ['id' => 1, 'product_id' => 101, 'quantity' => 1, 'unit_price' => '0.01'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        $this->assertEquals(0, $res['discount_cents']);
        $this->assertEquals(0.00, $res['discount_amount']);
    }

    /**
     * Test 1.04 [Global 04]: Percentage boundary: 3 cents * 50% = 2 cents.
     * Canonical formula: floor((3 * 5000 + 5000) / 10000) = floor(20000 / 10000) = 2 cents.
     */
    public function test_percentage_discount_boundary_3_cents_times_50_percent_equals_2_cents(): void
    {
        $coupon = Coupon::create([
            'code' => 'HALF50B',
            'name' => '50% Off',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 50.00,
            'is_active' => true,
        ]);

        $cartItems = [
            ['id' => 1, 'product_id' => 101, 'quantity' => 1, 'unit_price' => '0.03'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        $this->assertEquals(2, $res['discount_cents']);
        $this->assertEquals(0.02, $res['discount_amount']);
    }

    /**
     * Test 1.05 [Global 05]: Percentage boundary: LKR 999.99 * 15% = LKR 150.00.
     * 99999c * 1500 = 149998500 + 5000 = 150003500 / 10000 = 15000c = LKR 150.00.
     */
    public function test_percentage_discount_boundary_999_99_times_15_percent_equals_150_00(): void
    {
        $coupon = Coupon::create([
            'code' => 'PCT15B',
            'name' => '15% Off',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 15.00,
            'is_active' => true,
        ]);

        $cartItems = [
            ['id' => 1, 'product_id' => 101, 'quantity' => 1, 'unit_price' => '999.99'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        $this->assertEquals(15000, $res['discount_cents']);
        $this->assertEquals(150.00, $res['discount_amount']);
    }

    /**
     * Test 1.06 [Global 06]: Percentage coupon applies default 5,000 cap after half-up rounding.
     */
    public function test_percentage_coupon_applies_default_5000_cap_after_half_up_rounding(): void
    {
        $coupon = Coupon::create([
            'code' => 'PCT20',
            'name' => '20% Off Big Order',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 20.00,
            'is_active' => true,
            // Default cap LKR 5,000.00 (500,000 cents) applies automatically
        ]);

        // Subtotal = LKR 50,000.00. 20% would be 10,000.00, but capped at 5,000.00
        $cartItems = [
            ['id' => 1, 'product_id' => 101, 'quantity' => 50, 'unit_price' => '1000.00'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        $this->assertEquals(500000, $res['discount_cents']);
        $this->assertEquals(5000.00, $res['discount_amount']);
    }

    /**
     * Test 1.07 [Global 07]: Percentage coupon applies Owner custom cap exceeding 5,000.
     */
    public function test_percentage_coupon_applies_owner_custom_cap_exceeding_5000(): void
    {
        $coupon = Coupon::create([
            'code' => 'VIP20',
            'name' => 'VIP 20% Off',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 20.00,
            'max_discount_amount' => 8000.00, // Custom LKR 8,000.00 cap
            'is_active' => true,
        ]);

        // Subtotal = LKR 50,000.00. 20% would be 10,000.00. Capped at 8,000.00.
        $cartItems = [
            ['id' => 1, 'product_id' => 101, 'quantity' => 50, 'unit_price' => '1000.00'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        $this->assertEquals(800000, $res['discount_cents']);
        $this->assertEquals(8000.00, $res['discount_amount']);
    }

    /**
     * Test 1.08 [Global 08]: Percentage coupon unlimited cap bypasses default ceiling.
     */
    public function test_percentage_coupon_unlimited_cap_bypasses_default_ceiling(): void
    {
        $coupon = Coupon::create([
            'code' => 'NOCAP20',
            'name' => 'Unlimited 20% Off',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 20.00,
            'is_unlimited_cap' => true,
            'is_active' => true,
        ]);

        // Subtotal = LKR 50,000.00. 20% = LKR 10,000.00 uncapped.
        $cartItems = [
            ['id' => 1, 'product_id' => 101, 'quantity' => 50, 'unit_price' => '1000.00'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        $this->assertEquals(1000000, $res['discount_cents']);
        $this->assertEquals(10000.00, $res['discount_amount']);
    }

    /**
     * Test 1.09 [Global 09]: Non-owner forbidden from creating coupon with cap exceeding 5,000.
     */
    public function test_non_owner_forbidden_from_creating_coupon_with_cap_exceeding_5000(): void
    {
        $marketingUser = $this->createMarketingUser();

        // Attempting to set cap > 5000 as Marketing Manager must fail
        $this->expectException(AuthorizationException::class);
        $this->couponService->createCoupon([
            'code' => 'ILLEGAL_CAP',
            'name' => 'High Cap Attempt',
            'type' => 'percentage',
            'value' => 25.00,
            'max_discount_amount' => 7500.00, // Exceeds LKR 5,000.00
            'applies_to' => 'all',
        ], $marketingUser);
    }

    /**
     * Test 1.10 [Global 10]: Fixed amount coupon largest remainder allocation reconciles to exact cent.
     */
    public function test_fixed_amount_coupon_largest_remainder_allocation_reconciles_to_exact_cent(): void
    {
        $coupon = Coupon::create([
            'code' => 'FIXED100',
            'name' => 'LKR 100 Off',
            'type' => Coupon::TYPE_FIXED_AMOUNT,
            'value' => 100.00, // 10,000 cents
            'is_active' => true,
        ]);

        // 3 items with different prices: $1000, $2000, $3000 -> Total = $6000
        $cartItems = [
            ['id' => 1, 'product_id' => 1, 'quantity' => 1, 'unit_price' => '1000.00'],
            ['id' => 2, 'product_id' => 2, 'quantity' => 1, 'unit_price' => '2000.00'],
            ['id' => 3, 'product_id' => 3, 'quantity' => 1, 'unit_price' => '3000.00'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        $this->assertEquals(10000, $res['discount_cents']);

        // Sum of line allocations must equal exactly 10,000 cents
        $allocatedCentsSum = 0;
        foreach ($res['line_allocations'] as $alloc) {
            $allocatedCentsSum += $alloc['allocated_cents'];
        }
        $this->assertEquals(10000, $allocatedCentsSum);
    }

    /**
     * Test 1.11 [Global 11]: Percentage coupon largest remainder allocation reconciles to exact cent.
     */
    public function test_percentage_coupon_largest_remainder_allocation_reconciles_to_exact_cent(): void
    {
        $coupon = Coupon::create([
            'code' => 'PCT17',
            'name' => '17% Off',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 17.00,
            'is_active' => true,
        ]);

        // Cart with items creating non-trivial repeating decimals
        $cartItems = [
            ['id' => 1, 'product_id' => 1, 'quantity' => 1, 'unit_price' => '133.33'],
            ['id' => 2, 'product_id' => 2, 'quantity' => 2, 'unit_price' => '247.89'],
            ['id' => 3, 'product_id' => 3, 'quantity' => 1, 'unit_price' => '89.15'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);
        $orderDiscountCents = $res['discount_cents'];

        $allocatedCentsSum = 0;
        foreach ($res['line_allocations'] as $alloc) {
            $allocatedCentsSum += $alloc['allocated_cents'];
        }

        $this->assertEquals($orderDiscountCents, $allocatedCentsSum);
    }

    /**
     * Test 1.12 [Global 12]: Ineligible products receive zero allocated discount.
     */
    public function test_ineligible_products_receive_zero_allocated_discount(): void
    {
        $catA = Category::create(['name_en' => 'Skincare', 'name_si' => 'සම රැකවරණය', 'slug' => 'skincare']);
        $catB = Category::create(['name_en' => 'Haircare', 'name_si' => 'හිසකෙස්', 'slug' => 'haircare']);

        $coupon = Coupon::create([
            'code' => 'SKIN10',
            'name' => '10% Off Skincare Only',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10.00,
            'applies_to' => Coupon::APPLIES_CATEGORIES,
            'is_active' => true,
        ]);
        $coupon->categories()->attach($catA->id);

        $cartItems = [
            ['id' => 1, 'product_id' => 10, 'category_id' => $catA->id, 'quantity' => 1, 'unit_price' => '2000.00'],
            ['id' => 2, 'product_id' => 20, 'category_id' => $catB->id, 'quantity' => 1, 'unit_price' => '3000.00'], // Ineligible
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems);

        // Only Item 1 receives discount: 10% of 2000.00 = 200.00 (20,000 cents)
        $this->assertEquals(20000, $res['discount_cents']);
        $this->assertEquals(20000, $res['line_allocations'][0]['allocated_cents']);
        $this->assertArrayNotHasKey(1, $res['line_allocations']); // Item 2 received no discount
    }

    /**
     * Test 1.13 [Global 13]: Free shipping coupon sets delivery charge discount without affecting commission base.
     */
    public function test_free_shipping_coupon_sets_delivery_charge_discount_without_affecting_commission_base(): void
    {
        $coupon = Coupon::create([
            'code' => 'FREESHIP',
            'name' => 'Free Delivery',
            'type' => Coupon::TYPE_FREE_SHIPPING,
            'value' => 0.00,
            'is_active' => true,
        ]);

        $cartItems = [
            ['id' => 1, 'product_id' => 10, 'quantity' => 1, 'unit_price' => '2500.00'],
        ];

        $res = $this->couponService->validateAndCalculate($coupon, $cartItems, deliveryCharge: 350.00);

        $this->assertEquals(0, $res['discount_cents']);
        $this->assertEquals(0.00, $res['discount_amount']);
        $this->assertEquals(35000, $res['shipping_discount_cents']);
        $this->assertEquals(350.00, $res['shipping_discount_amount']);

        // Check Commission base: Order with 2500.00 subtotal & free shipping retains full 2500.00 commission base
        $order = new Order([
            'subtotal' => 2500.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 350.00,
            'shipping_discount_amount' => 350.00,
            'total_amount' => 2500.00,
        ]);
        $this->assertEquals('2500.00', $order->netDiscountedProductSubtotal());
    }

    /**
     * Test 1.14 [Global 14]: Coupon minimum subtotal evaluated against eligible products only.
     */
    public function test_coupon_minimum_subtotal_evaluated_against_eligible_products_only(): void
    {
        $catA = Category::create(['name_en' => 'Creams', 'name_si' => 'ක්‍රීම්', 'slug' => 'creams']);
        $catB = Category::create(['name_en' => 'Perfume', 'name_si' => 'සුවඳ විලවුන්', 'slug' => 'perfume']);

        $coupon = Coupon::create([
            'code' => 'MIN3000',
            'name' => 'Creams Min LKR 3,000',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10.00,
            'min_order_subtotal' => 3000.00,
            'applies_to' => Coupon::APPLIES_CATEGORIES,
            'is_active' => true,
        ]);
        $coupon->categories()->attach($catA->id);

        // Eligible Creams item is only LKR 2,000.00, though total cart subtotal is LKR 5,000.00
        $cartItems = [
            ['id' => 1, 'product_id' => 1, 'category_id' => $catA->id, 'quantity' => 1, 'unit_price' => '2000.00'], // $2,000 eligible
            ['id' => 2, 'product_id' => 2, 'category_id' => $catB->id, 'quantity' => 1, 'unit_price' => '3000.00'], // $3,000 ineligible
        ];

        $this->expectException(ValidationException::class);
        $this->couponService->validateAndCalculate($coupon, $cartItems);
    }

    /**
     * Test 1.15 [Global 15]: Expired or inactive coupon rejected at validation.
     */
    public function test_expired_or_inactive_coupon_rejected_at_validation(): void
    {
        $inactive = Coupon::create([
            'code' => 'OFFLINE',
            'name' => 'Inactive Coupon',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10.00,
            'is_active' => false,
        ]);

        $cart = [['id' => 1, 'product_id' => 1, 'quantity' => 1, 'unit_price' => '1000.00']];

        try {
            $this->couponService->validateAndCalculate($inactive, $cart);
            $this->fail('Expected inactive coupon to throw ValidationException');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        $expired = Coupon::create([
            'code' => 'EXPIRED',
            'name' => 'Old Coupon',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10.00,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->expectException(ValidationException::class);
        $this->couponService->validateAndCalculate($expired, $cart);
    }

    /**
     * Test 1.16 [Global 16]: Customer per-user usage limit enforced strictly.
     */
    public function test_customer_per_user_usage_limit_enforced_strictly(): void
    {
        $customer = $this->createCustomerUser('single@example.com');
        $coupon = Coupon::create([
            'code' => 'ONCE',
            'name' => 'One Time Use',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10.00,
            'usage_limit_per_customer' => 1,
            'is_active' => true,
        ]);

        $cart = [['id' => 1, 'product_id' => 1, 'quantity' => 1, 'unit_price' => '1000.00']];

        // First validation succeeds
        $res = $this->couponService->validateAndCalculate($coupon, $cart, customer: $customer);
        $this->assertNotEmpty($res);

        // Record usage for this customer on a valid order
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => '0771234567',
            'customer_email' => $customer->email,
            'shipping_address' => '123 Main St',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 1000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 1350.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => Order::generateTrackingToken(),
            'placed_at' => now(),
        ]);

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_phone' => '0771234567',
            'discount_amount' => 100.00,
            'shipping_discount_amount' => 0.00,
            'created_at' => now(),
        ]);

        // Second validation must fail
        $this->expectException(ValidationException::class);
        $this->couponService->validateAndCalculate($coupon, $cart, customer: $customer);
    }

    /**
     * Test 1.17 [Global 17]: Global usage limit enforced strictly.
     */
    public function test_global_usage_limit_enforced_strictly(): void
    {
        $coupon = Coupon::create([
            'code' => 'LIMITED2',
            'name' => 'Limited 2 Redemptions',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10.00,
            'usage_limit_total' => 2,
            'times_used' => 2, // Reached limit
            'is_active' => true,
        ]);

        $cart = [['id' => 1, 'product_id' => 1, 'quantity' => 1, 'unit_price' => '1000.00']];

        $this->expectException(ValidationException::class);
        $this->couponService->validateAndCalculate($coupon, $cart);
    }

    /**
     * Test 1.18 [Global 18]: Order cancellation restores coupon usage count.
     */
    public function test_order_cancellation_restores_coupon_usage_count(): void
    {
        $customer = $this->createCustomerUser();
        $product = $this->createTestProduct(2000.00);

        $coupon = Coupon::create([
            'code' => 'RESTOREME',
            'name' => 'Restore Test',
            'type' => Coupon::TYPE_FIXED_AMOUNT,
            'value' => 500.00,
            'times_used' => 0,
            'is_active' => true,
        ]);

        // Place order with coupon
        $order = $this->orderService->createOrder(
            customerData: [
                'customer_name' => 'Jane Doe',
                'customer_phone' => '0771234567',
                'customer_email' => 'jane@example.com',
                'shipping_address' => '123 Galle Road, Colombo',
                'district' => 'Colombo',
                'city' => 'Colombo 03',
            ],
            rawCart: [$product->id => 1],
            authenticatedUser: $customer,
            couponCode: 'RESTOREME'
        );

        $coupon->refresh();
        $this->assertEquals(1, $coupon->times_used);

        // Cancel / restore order
        $restored = $this->couponService->restoreCouponUsage($order);
        $this->assertTrue($restored);

        $coupon->refresh();
        $this->assertEquals(0, $coupon->times_used);

        // Idempotent: second restore returns false and does not decrement further
        $secondRestore = $this->couponService->restoreCouponUsage($order);
        $this->assertFalse($secondRestore);
        $coupon->refresh();
        $this->assertEquals(0, $coupon->times_used);
    }

    /**
     * Test 1.19 [Global 19]: Concurrent coupon applications prevent usage limit oversubscription.
     */
    public function test_concurrent_coupon_applications_prevent_usage_limit_oversubscription(): void
    {
        $customer = $this->createCustomerUser();
        $product = $this->createTestProduct(2000.00);

        $coupon = Coupon::create([
            'code' => 'LASTONE',
            'name' => 'Only 1 Available',
            'type' => Coupon::TYPE_FIXED_AMOUNT,
            'value' => 200.00,
            'usage_limit_total' => 1,
            'times_used' => 0,
            'is_active' => true,
        ]);

        // Order 1 succeeds
        $order1 = $this->orderService->createOrder(
            customerData: [
                'customer_name' => 'Buyer 1',
                'customer_phone' => '0771111111',
                'customer_email' => 'buyer1@example.com',
                'shipping_address' => '456 Kandy Road',
                'district' => 'Kandy',
                'city' => 'Kandy',
            ],
            rawCart: [$product->id => 1],
            couponCode: 'LASTONE'
        );
        $this->assertNotNull($order1->id);

        $coupon->refresh();
        $this->assertEquals(1, $coupon->times_used);

        // Order 2 with same coupon must be rejected immediately
        $this->expectException(ValidationException::class);
        $this->orderService->createOrder(
            customerData: [
                'customer_name' => 'Buyer 2',
                'customer_phone' => '0772222222',
                'customer_email' => 'buyer2@example.com',
                'shipping_address' => '789 Matara Road',
                'district' => 'Matara',
                'city' => 'Matara',
            ],
            rawCart: [$product->id => 1],
            couponCode: 'LASTONE'
        );
    }

    /**
     * Test 1.20 [Global 20]: Super admin forbidden from toggling coupon active status.
     */
    public function test_super_admin_forbidden_from_toggling_coupon_active_status(): void
    {
        $superAdmin = $this->createSuperAdminUser();

        $coupon = Coupon::create([
            'code' => 'PROMOTOGGLE',
            'name' => 'Toggle Test',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10.00,
            'is_active' => true,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->couponService->toggleActive($coupon, $superAdmin);
    }
}
