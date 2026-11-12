<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CodCollection;
use App\Models\CommissionLedgerEntry;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\TwoFactorAuthService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorAuthService $twoFactorService;
    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $agent;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->twoFactorService = app(TwoFactorAuthService::class);

        // Owner (Business Authority)
        $this->owner = User::where('role', UserRole::OWNER)->first()
            ?? User::factory()->create([
                'name' => 'Dilshan Business Owner',
                'email' => 'owner@auraluxe.lk',
                'role' => UserRole::OWNER,
                'protected_authority' => 'business',
                'status' => UserStatus::ACTIVE,
                'password' => bcrypt('SecurePassword123!'),
            ]);
        $this->owner->update(['password' => bcrypt('SecurePassword123!')]);

        // Super Admin (Technical Authority)
        $this->superAdmin = User::where('role', UserRole::SUPER_ADMIN)->first()
            ?? User::factory()->create([
                'name' => 'Naveen Super Admin',
                'email' => 'superadmin@auraluxe.lk',
                'role' => UserRole::SUPER_ADMIN,
                'protected_authority' => 'technical',
                'status' => UserStatus::ACTIVE,
                'password' => bcrypt('SecurePassword123!'),
            ]);
        $this->superAdmin->update(['password' => bcrypt('SecurePassword123!')]);

        // Agent Manager (Management role)
        $this->agentManager = User::factory()->create([
            'name' => 'Agent Manager',
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
            'email' => 'manager@auraluxe.lk',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        // Agent (Non-management)
        $this->agent = User::factory()->create([
            'name' => 'High Level Agent',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'email' => 'agent@auraluxe.lk',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        // Customer (Non-management)
        $this->customer = User::factory()->create([
            'name' => 'Store Customer',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'email' => 'customer@auraluxe.lk',
            'password' => bcrypt('SecurePassword123!'),
        ]);
    }

    private function createTestOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'customer_name' => 'Test Customer',
            'customer_phone' => '0771234567',
            'shipping_address' => '123 Galle Road',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'subtotal' => 3000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3350.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => Str::random(32),
            'placed_at' => now(),
        ], $overrides));
    }

    private function createTestProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name_en' => 'Organic Rose Serum',
            'name_si' => 'කාබනික රෝස සීරම්',
            'slug' => 'organic-rose-serum-' . Str::random(5),
            'sku' => 'SKU-' . strtoupper(Str::random(6)),
            'price' => 3000.00,
            'stock_quantity' => 50,
            'is_active' => true,
        ], $overrides));
    }

    // =========================================================================
    // 1. MANDATORY MFA ENFORCEMENT TESTS
    // =========================================================================

    public function test_newly_created_management_account_is_redirected_to_mfa_setup_on_login(): void
    {
        $this->assertFalse($this->agentManager->hasTwoFactorEnabled());

        $response = $this->post(route('login'), [
            'email' => $this->agentManager->email,
            'password' => 'SecurePassword123!',
        ]);

        // Must redirect directly to 2FA setup, NOT dashboard
        $response->assertRedirect(route('two-factor.setup'));
        $this->assertAuthenticatedAs($this->agentManager);
    }

    public function test_unenrolled_management_account_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->agentManager);
        session(['two_factor_test_enforce' => true]);
        $this->assertFalse($this->agentManager->hasTwoFactorEnabled());

        $response = $this->get(route('admin.dashboard'));

        // Strictly intercepted by EnsureTwoFactorAuthenticated
        $response->assertRedirect(route('two-factor.setup'));
    }

    public function test_unenrolled_management_account_cannot_access_profile_or_normal_features(): void
    {
        $this->actingAs($this->agentManager);
        session(['two_factor_test_enforce' => true]);
        $this->assertFalse($this->agentManager->hasTwoFactorEnabled());

        // Attempting to access profile
        $response = $this->get(route('profile.edit'));
        $response->assertRedirect(route('two-factor.setup'));

        // Attempting to access staff index
        $adminAttempt = $this->get(route('admin.staff.index'));
        $adminAttempt->assertRedirect(route('two-factor.setup'));
    }

    public function test_unenrolled_management_account_can_access_only_enrollment_routes_and_logout(): void
    {
        $this->actingAs($this->agentManager);
        $this->assertFalse($this->agentManager->hasTwoFactorEnabled());

        // Setup page is accessible
        $this->get(route('two-factor.setup'))->assertOk();

        // Logout is accessible
        $logoutResponse = $this->post(route('logout'));
        $logoutResponse->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_unenrolled_management_account_can_complete_mfa_enrollment(): void
    {
        $this->actingAs($this->agentManager);

        // Load setup page
        $this->get(route('two-factor.setup'))->assertOk();
        $secret = session('two_factor_pending_secret');
        $this->assertNotEmpty($secret);

        $validCode = $this->twoFactorService->calculateCode($secret);

        $response = $this->post(route('two-factor.confirm'), [
            'code' => $validCode,
        ]);

        $response->assertRedirect(route('two-factor.recovery-codes'));
        $this->assertTrue($this->agentManager->fresh()->hasTwoFactorEnabled());
        $this->assertTrue(session('two_factor_authenticated'));

        // Now admin dashboard is accessible
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_enrolled_management_account_on_login_is_challenged_and_cannot_bypass_to_setup(): void
    {
        // Enroll agent manager
        $secret = $this->twoFactorService->generateSecretKey();
        $code = $this->twoFactorService->calculateCode($secret);
        $this->twoFactorService->confirmTwoFactor($this->agentManager, $secret, $code);

        $this->post(route('logout'));

        // Login
        $response = $this->post(route('login'), [
            'email' => $this->agentManager->email,
            'password' => 'SecurePassword123!',
        ]);

        $response->assertRedirect(route('two-factor.challenge'));

        // Attempting to hit two-factor.setup to overwrite secret without authenticating is strictly blocked
        $this->get(route('two-factor.setup'))->assertRedirect(route('two-factor.challenge'));
    }

    public function test_customer_and_agent_are_never_subject_to_mfa_enforcement(): void
    {
        // Customer login
        $response = $this->post(route('login'), [
            'email' => $this->customer->email,
            'password' => 'SecurePassword123!',
        ]);
        $response->assertRedirect(route('dashboard'));

        $this->actingAs($this->customer);
        $this->get(route('profile.edit'))->assertOk();

        $this->post(route('logout'));

        // Agent login
        $agentResponse = $this->post(route('login'), [
            'email' => $this->agent->email,
            'password' => 'SecurePassword123!',
        ]);
        $agentResponse->assertRedirect(route('dashboard'));

        $this->actingAs($this->agent);
        $this->get(route('agent.dashboard'))->assertOk();
    }

    public function test_user_totp_secret_is_never_exposed_in_model_serialization(): void
    {
        $secret = $this->twoFactorService->generateSecretKey();
        $code = $this->twoFactorService->calculateCode($secret);
        $this->twoFactorService->confirmTwoFactor($this->owner, $secret, $code);

        $serialized = $this->owner->fresh()->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $serialized);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $serialized);
    }

    public function test_cross_privileged_mfa_reset_is_forbidden(): void
    {
        // Enroll both authorities so they pass MFA middleware and hit the controller protection
        $this->owner->update([
            'two_factor_secret' => 'SECRETKEY1234567890123456789012',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superAdmin->update([
            'two_factor_secret' => 'SECRETKEY1234567890123456789012',
            'two_factor_confirmed_at' => now(),
        ]);

        // 1. Super Admin cannot reset Owner's MFA
        $this->actingAs($this->superAdmin);
        session(['two_factor_authenticated' => true]);

        $response = $this->post(route('admin.staff.reset-mfa', $this->owner->id));
        $response->assertStatus(403);

        // 2. Owner cannot reset Super Admin's MFA
        $this->actingAs($this->owner);
        session(['two_factor_authenticated' => true]);

        $response2 = $this->post(route('admin.staff.reset-mfa', $this->superAdmin->id));
        $response2->assertStatus(403);
    }

    // =========================================================================
    // 2. FINANCIAL RECORD DELETION PROTECTION TESTS
    // =========================================================================

    public function test_order_with_commission_ledger_entries_cannot_be_deleted(): void
    {
        $order = $this->createTestOrder([
            'subtotal' => 5000.00,
            'total_amount' => 5350.00,
        ]);

        CommissionLedgerEntry::create([
            'entry_number' => 'CLE-TEST-001',
            'order_id' => $order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => 'direct_high_level',
            'base_amount' => 5000.00,
            'rate_percentage' => 25.00,
            'calculated_amount' => 1250.00,
            'status' => 'pending',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Integrity Violation: Order #{$order->order_number} has associated commission ledger records and cannot be deleted.");

        $order->delete();
    }

    public function test_order_with_collected_cod_cannot_be_deleted(): void
    {
        $order = $this->createTestOrder([
            'subtotal' => 2500.00,
            'total_amount' => 2850.00,
            'status' => 'completed',
        ]);

        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 2850.00,
            'collected_amount' => 2850.00,
            'reconciliation_status' => 'matched',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Integrity Violation: Order #{$order->order_number} has recorded COD collections and cannot be deleted.");

        $order->delete();
    }

    public function test_order_with_inventory_movements_cannot_be_deleted(): void
    {
        $product = $this->createTestProduct(['stock_quantity' => 100]);
        $order = $this->createTestOrder();

        InventoryMovement::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'movement_type' => InventoryMovement::TYPE_ORDER_DEDUCTION,
            'quantity' => -2,
            'previous_stock' => 100,
            'new_stock' => 98,
            'reason' => 'Customer Order',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Integrity Violation: Order #{$order->order_number} has associated inventory movements and cannot be deleted.");

        $order->delete();
    }

    public function test_user_with_commissions_cannot_be_deleted(): void
    {
        $order = $this->createTestOrder();

        CommissionLedgerEntry::create([
            'entry_number' => 'CLE-TEST-002',
            'order_id' => $order->id,
            'agent_id' => $this->agent->id,
            'selling_agent_id' => $this->agent->id,
            'commission_type' => 'direct_high_level',
            'base_amount' => 1000.00,
            'rate_percentage' => 25.00,
            'calculated_amount' => 250.00,
            'status' => 'pending',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Integrity Violation: User '{$this->agent->name}' ({$this->agent->email}) has associated commission ledger records and cannot be deleted.");

        $this->agent->delete();
    }

    public function test_user_with_wallet_transactions_cannot_be_deleted(): void
    {
        WalletTransaction::create([
            'transaction_number' => 'TXN-TEST-001',
            'agent_id' => $this->agent->id,
            'transaction_type' => WalletTransaction::TYPE_COMMISSION_CREDIT,
            'amount' => 500.00,
            'balance_after' => 500.00,
            'description' => 'Commission credit',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Integrity Violation: User '{$this->agent->name}' ({$this->agent->email}) has wallet transaction history and cannot be deleted.");

        $this->agent->delete();
    }

    public function test_user_with_payout_requests_cannot_be_deleted(): void
    {
        PayoutRequest::create([
            'reference_number' => 'PAY-TEST-001',
            'agent_id' => $this->agent->id,
            'amount' => 1500.00,
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Agent Name',
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Integrity Violation: User '{$this->agent->name}' ({$this->agent->email}) has commission payout records and cannot be deleted.");

        $this->agent->delete();
    }

    // =========================================================================
    // 3. PUBLIC ORDER TRACKING & RATE LIMITING TESTS
    // =========================================================================

    public function test_public_order_tracking_permits_valid_lookup(): void
    {
        RateLimiter::clear(sha1('15|127.0.0.1'));
        RateLimiter::clear(sha1('track|127.0.0.1'));

        $order = $this->createTestOrder([
            'order_number' => 'ORD-TRACK-001',
            'customer_phone' => '0771234567',
        ]);

        $response = $this->post(route('tracking.track'), [
            'order_number' => 'ORD-TRACK-001',
            'phone' => '0771234567',
        ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/Index')
            ->where('searched', true)
            ->where('order.order_number', 'ORD-TRACK-001')
        );
    }

    public function test_tracking_lookup_does_not_leak_order_existence_on_phone_mismatch(): void
    {
        $this->createTestOrder([
            'order_number' => 'ORD-TRACK-SECRET',
            'customer_phone' => '0771112233',
        ]);

        // Wrong phone
        $response = $this->post(route('tracking.track'), [
            'order_number' => 'ORD-TRACK-SECRET',
            'phone' => '0779998877',
        ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/Index')
            ->where('searched', true)
            ->where('order', null) // Returns empty result without disclosing order exists
        );
    }

    public function test_public_tracking_endpoint_rate_limits_after_fifteen_requests(): void
    {
        // Execute 15 valid requests
        for ($i = 0; $i < 15; $i++) {
            $response = $this->post(route('tracking.track'), [
                'order_number' => "ORD-TEST-{$i}",
                'phone' => '0770000000',
            ]);
            $response->assertOk();
        }

        // 16th request must trigger HTTP 429 Too Many Requests
        $rateLimitedResponse = $this->post(route('tracking.track'), [
            'order_number' => 'ORD-TEST-EXCESS',
            'phone' => '0770000000',
        ]);

        $rateLimitedResponse->assertStatus(429);
    }
}
