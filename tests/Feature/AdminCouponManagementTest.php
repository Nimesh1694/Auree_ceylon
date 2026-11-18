<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCouponManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function createOwner(): User
    {
        return User::factory()->create([
            'role' => UserRole::OWNER->value,
            'email' => 'owner@example.com',
        ]);
    }

    protected function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'email' => 'superadmin@example.com',
        ]);
    }

    protected function createMarketingManager(): User
    {
        return User::factory()->create([
            'role' => UserRole::MARKETING_MANAGER->value,
            'email' => 'marketing@example.com',
        ]);
    }

    protected function createCustomer(): User
    {
        return User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'email' => 'cust@example.com',
        ]);
    }

    public function test_owner_can_view_coupons_list(): void
    {
        $owner = $this->createOwner();

        Coupon::create([
            'code' => 'TEST10',
            'name' => 'Test 10%',
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->get('/admin/coupons');
        $response->assertStatus(200);
    }

    public function test_marketing_manager_can_view_coupons_list(): void
    {
        $marketing = $this->createMarketingManager();

        $response = $this->actingAs($marketing)->get('/admin/coupons');
        $response->assertStatus(200);
    }

    public function test_customer_forbidden_from_viewing_admin_coupons(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)->get('/admin/coupons');
        // Customers are not management staff -> redirected or 403 by role:management middleware
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    public function test_marketing_manager_can_create_standard_coupon(): void
    {
        $marketing = $this->createMarketingManager();

        $response = $this->actingAs($marketing)->post('/admin/coupons', [
            'code' => 'SUMMER15',
            'name' => 'Summer Sale 15%',
            'type' => 'percentage',
            'value' => 15.00,
            'min_order_subtotal' => 2000.00,
            'max_discount_amount' => 3000.00,
            'is_unlimited_cap' => false,
            'usage_limit_total' => 100,
            'usage_limit_per_customer' => 1,
            'is_active' => true,
            'is_public' => true,
            'applies_to' => 'all',
            'allow_agent_referral' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupons', ['code' => 'SUMMER15']);
    }

    public function test_super_admin_forbidden_from_creating_coupon(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->post('/admin/coupons', [
            'code' => 'HACK100',
            'name' => 'Super Admin Coupon',
            'type' => 'percentage',
            'value' => 100.00,
            'applies_to' => 'all',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('coupons', ['code' => 'HACK100']);
    }

    public function test_owner_can_authorize_unlimited_cap(): void
    {
        $owner = $this->createOwner();

        $response = $this->actingAs($owner)->post('/admin/coupons', [
            'code' => 'OWNER_UNLIMITED',
            'name' => 'Unlimited VIP Promo',
            'type' => 'percentage',
            'value' => 20.00,
            'is_unlimited_cap' => true,
            'applies_to' => 'all',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupons', [
            'code' => 'OWNER_UNLIMITED',
            'is_unlimited_cap' => true,
        ]);
    }

    public function test_marketing_manager_forbidden_from_authorizing_unlimited_cap(): void
    {
        $marketing = $this->createMarketingManager();

        $response = $this->actingAs($marketing)->post('/admin/coupons', [
            'code' => 'UNAUTH_UNLIMITED',
            'name' => 'Unauthorized Promo',
            'type' => 'percentage',
            'value' => 20.00,
            'is_unlimited_cap' => true,
            'applies_to' => 'all',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('coupons', ['code' => 'UNAUTH_UNLIMITED']);
    }

    public function test_marketing_manager_can_toggle_coupon_status(): void
    {
        $marketing = $this->createMarketingManager();

        $coupon = Coupon::create([
            'code' => 'TOGGLEME',
            'name' => 'Toggle Coupon',
            'type' => 'fixed_amount',
            'value' => 250.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketing)->patch("/admin/coupons/{$coupon->id}/toggle");
        $response->assertRedirect();

        $coupon->refresh();
        $this->assertFalse($coupon->is_active);
    }

    public function test_super_admin_forbidden_from_toggling_coupon_status(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $coupon = Coupon::create([
            'code' => 'TOGGLEFAIL',
            'name' => 'Toggle Fail',
            'type' => 'fixed_amount',
            'value' => 250.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->patch("/admin/coupons/{$coupon->id}/toggle");
        $response->assertStatus(403);

        $coupon->refresh();
        $this->assertTrue($coupon->is_active);
    }

    public function test_owner_can_delete_unused_coupon(): void
    {
        $owner = $this->createOwner();

        $coupon = Coupon::create([
            'code' => 'DELETEUS',
            'name' => 'To Be Deleted',
            'type' => 'fixed_amount',
            'value' => 100.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->delete("/admin/coupons/{$coupon->id}");
        $response->assertRedirect('/admin/coupons');

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_marketing_manager_forbidden_from_deleting_coupon(): void
    {
        $marketing = $this->createMarketingManager();

        $coupon = Coupon::create([
            'code' => 'NODELETE',
            'name' => 'Cannot Delete',
            'type' => 'fixed_amount',
            'value' => 100.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketing)->delete("/admin/coupons/{$coupon->id}");
        $response->assertStatus(403);

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }

    public function test_public_checkout_can_apply_and_remove_coupon(): void
    {
        $product = Product::create([
            'name_en' => 'Organic Toner',
            'name_si' => 'ටෝනර්',
            'slug' => 'organic-toner',
            'sku' => 'TONER-001',
            'price' => 3000.00,
            'cost_price' => 1000.00,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        $coupon = Coupon::create([
            'code' => 'TONER500',
            'name' => '500 LKR Off',
            'type' => Coupon::TYPE_FIXED_AMOUNT,
            'value' => 500.00,
            'is_active' => true,
        ]);

        // Add to cart
        $this->post('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        // Apply coupon
        $applyRes = $this->post('/checkout/apply-coupon', [
            'coupon_code' => 'TONER500',
        ]);
        $applyRes->assertRedirect();
        $this->assertEquals('TONER500', session('applied_coupon_code'));

        // Remove coupon
        $removeRes = $this->post('/checkout/remove-coupon');
        $removeRes->assertRedirect();
        $this->assertNull(session('applied_coupon_code'));
    }
}

