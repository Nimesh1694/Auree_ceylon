<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $superAdmin;
    private User $operationsManager;
    private User $marketingManager;
    private User $agentManager;
    private User $agent;
    private User $customer1;
    private User $customer2;
    private User $suspendedCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Owner Person',
            'email' => 'owner@auraluxe.lk',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'name' => 'Super Admin Person',
            'email' => 'admin@auraluxe.lk',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->operationsManager = User::factory()->create([
            'role' => UserRole::OPERATIONS_MANAGER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Ops Manager Person',
            'email' => 'ops@auraluxe.lk',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->marketingManager = User::factory()->create([
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Marketing Manager Person',
            'email' => 'marketing@auraluxe.lk',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->agentManager = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Agent Manager Person',
            'email' => 'agentmgr@auraluxe.lk',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'name' => 'HLA Agent Person',
            'email' => 'hla@auraluxe.lk',
        ]);

        $this->customer1 = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Kasun Perera',
            'email' => 'kasun@example.com',
            'phone' => '0771112222',
        ]);

        $this->customer2 = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Anura Kumara',
            'email' => 'anura@example.com',
            'phone' => '0713334444',
        ]);

        $this->suspendedCustomer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::SUSPENDED,
            'name' => 'Suspended Customer',
            'email' => 'suspended@example.com',
            'phone' => '0779998888',
        ]);
    }

    // ==========================================
    // 1. DIRECTORY LISTING & EXCLUSION TESTS
    // ==========================================

    public function test_authorized_management_roles_can_view_customer_directory(): void
    {
        // Owner can access
        $response = $this->actingAs($this->owner)->get(route('admin.customers.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Customers/Index'));

        // Super Admin can access
        $response = $this->actingAs($this->superAdmin)->get(route('admin.customers.index'));
        $response->assertOk();

        // Operations Manager can access
        $response = $this->actingAs($this->operationsManager)->get(route('admin.customers.index'));
        $response->assertOk();

        // Marketing Manager can access
        $response = $this->actingAs($this->marketingManager)->get(route('admin.customers.index'));
        $response->assertOk();
    }

    public function test_customer_directory_only_includes_customer_role_and_excludes_staff_and_agents(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.customers.index'));
        $response->assertOk();

        $response->assertInertia(function ($page) {
            $customerIds = collect($page->toArray()['props']['customers']['data'])->pluck('id')->all();

            // Customers are present
            $this->assertContains($this->customer1->id, $customerIds);
            $this->assertContains($this->customer2->id, $customerIds);
            $this->assertContains($this->suspendedCustomer->id, $customerIds);

            // Staff & Agents are strictly absent
            $this->assertNotContains($this->owner->id, $customerIds);
            $this->assertNotContains($this->superAdmin->id, $customerIds);
            $this->assertNotContains($this->operationsManager->id, $customerIds);
            $this->assertNotContains($this->agent->id, $customerIds);
        });
    }

    public function test_customer_directory_searches_by_name_email_and_phone(): void
    {
        // Search by name
        $response = $this->actingAs($this->owner)->get(route('admin.customers.index', ['search' => 'Kasun']));
        $response->assertOk();
        $response->assertInertia(function ($page) {
            $data = $page->toArray()['props']['customers']['data'];
            $this->assertCount(1, $data);
            $this->assertEquals('Kasun Perera', $data[0]['name']);
        });

        // Search by phone
        $response = $this->actingAs($this->owner)->get(route('admin.customers.index', ['search' => '0713334444']));
        $response->assertOk();
        $response->assertInertia(function ($page) {
            $data = $page->toArray()['props']['customers']['data'];
            $this->assertCount(1, $data);
            $this->assertEquals('Anura Kumara', $data[0]['name']);
        });
    }

    public function test_customer_directory_filters_by_account_status(): void
    {
        // Filter active
        $response = $this->actingAs($this->owner)->get(route('admin.customers.index', ['status' => 'active']));
        $response->assertOk();
        $response->assertInertia(function ($page) {
            $data = $page->toArray()['props']['customers']['data'];
            $this->assertCount(2, $data);
            foreach ($data as $c) {
                $this->assertEquals('active', $c['status']);
            }
        });

        // Filter suspended
        $response = $this->actingAs($this->owner)->get(route('admin.customers.index', ['status' => 'suspended']));
        $response->assertOk();
        $response->assertInertia(function ($page) {
            $data = $page->toArray()['props']['customers']['data'];
            $this->assertCount(1, $data);
            $this->assertEquals('Suspended Customer', $data[0]['name']);
        });
    }

    // ==========================================
    // 2. CUSTOMER PROFILE & ORDER HISTORY TESTS
    // ==========================================

    public function test_authorized_role_can_view_customer_profile_with_addresses_and_orders(): void
    {
        // Create an address for customer 1
        CustomerAddress::create([
            'user_id' => $this->customer1->id,
            'label' => 'Home Villa',
            'recipient_name' => 'Kasun Perera',
            'phone' => '0771112222',
            'address_line_1' => 'No 45 Sea View Road',
            'city' => 'Colombo 03',
            'district' => 'Colombo',
            'is_default' => true,
        ]);

        // Create an order for customer 1
        Order::create([
            'order_number' => 'ORD-TEST-0001',
            'customer_id' => $this->customer1->id,
            'customer_name' => 'Kasun Perera',
            'customer_phone' => '0771112222',
            'shipping_address' => 'No 45 Sea View Road',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'subtotal' => 5000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 5350.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'tok_0001_test',
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.customers.show', $this->customer1->id));
        $response->assertOk();
        $response->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $this->assertEquals('Kasun Perera', $props['customer']['name']);
            $this->assertCount(1, $props['addresses']);
            $this->assertEquals('Home Villa', $props['addresses'][0]['label']);
            $this->assertCount(1, $props['orders']['data']);
            $this->assertEquals('ORD-TEST-0001', $props['orders']['data'][0]['order_number']);
            $this->assertEquals(5350.00, $props['metrics']['total_spent']);
            $this->assertEquals(1, $props['metrics']['total_orders']);
        });
    }

    public function test_customer_order_history_does_not_leak_commissions_or_cost_prices(): void
    {
        Order::create([
            'order_number' => 'ORD-LEAK-TEST',
            'customer_id' => $this->customer1->id,
            'customer_name' => 'Kasun Perera',
            'customer_phone' => '0771112222',
            'shipping_address' => 'Sample Address',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 3000.00,
            'total_amount' => 3350.00,
            'tracking_token' => 'tok_leak_test',
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.customers.show', $this->customer1->id));
        $response->assertOk();
        $response->assertInertia(function ($page) {
            $orderData = $page->toArray()['props']['orders']['data'][0];

            // Verify customer-facing properties exist
            $this->assertArrayHasKey('order_number', $orderData);
            $this->assertArrayHasKey('total_amount', $orderData);

            // Verify sensitive financial internal keys are absent
            $this->assertArrayNotHasKey('commissions', $orderData);
            $this->assertArrayNotHasKey('cost_price', $orderData);
            $this->assertArrayNotHasKey('actual_courier_fee', $orderData);
        });
    }

    // ==========================================
    // 3. IDOR & ROLE TAMPERING PREVENTION TESTS
    // ==========================================

    public function test_customer_detail_returns_404_if_target_user_is_not_a_customer(): void
    {
        // Attempting to inspect Owner ID returns 404 (IDOR guard)
        $response = $this->actingAs($this->superAdmin)->get(route('admin.customers.show', $this->owner->id));
        $response->assertNotFound();

        // Attempting to inspect Agent ID returns 404
        $response = $this->actingAs($this->superAdmin)->get(route('admin.customers.show', $this->agent->id));
        $response->assertNotFound();
    }

    public function test_unauthorized_roles_are_forbidden_from_customer_directory(): void
    {
        // 1. Customer cannot access admin customer directory
        $response = $this->actingAs($this->customer1)->get(route('admin.customers.index'));
        $response->assertForbidden();

        // 2. Agent cannot access admin customer directory
        $response = $this->actingAs($this->agent)->get(route('admin.customers.index'));
        $response->assertForbidden();

        // 3. Agent Manager cannot access customer directory
        $response = $this->actingAs($this->agentManager)->get(route('admin.customers.index'));
        $response->assertForbidden();
    }

    public function test_non_customer_accounts_cannot_be_suspended_via_customer_status_endpoint(): void
    {
        // Attempting to suspend Owner returns 404
        $response = $this->actingAs($this->superAdmin)->post(route('admin.customers.status', $this->owner->id), [
            'status' => 'suspended',
            'reason' => 'Malicious attempt',
        ]);
        $response->assertNotFound();

        // Owner status is untouched
        $this->assertEquals(UserStatus::ACTIVE, $this->owner->fresh()->status);
    }

    // ==========================================
    // 4. SUSPENSION, REACTIVATION & AUDIT TESTS
    // ==========================================

    public function test_active_customer_can_be_suspended_with_mandatory_reason(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.customers.status', $this->customer1->id), [
            'status' => 'suspended',
            'reason' => 'Frequent fake orders and COD refusals.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(UserStatus::SUSPENDED, $this->customer1->fresh()->status);

        // Audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id' => $this->customer1->id,
            'action' => 'customer_suspended',
        ]);

        $log = AuditLog::where('auditable_id', $this->customer1->id)->latest('id')->first();
        $this->assertEquals('Frequent fake orders and COD refusals.', $log->new_values['reason']);
        $this->assertEquals($this->owner->id, $log->new_values['actor_id']);
    }

    public function test_suspending_customer_requires_a_meaningful_reason(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.customers.status', $this->customer1->id), [
            'status' => 'suspended',
            'reason' => '   ', // Blank whitespace!
        ]);

        $response->assertSessionHasErrors(['reason']);
        $this->assertEquals(UserStatus::ACTIVE, $this->customer1->fresh()->status);
    }

    public function test_suspended_customer_can_be_reactivated(): void
    {
        $response = $this->actingAs($this->operationsManager)->post(route('admin.customers.status', $this->suspendedCustomer->id), [
            'status' => 'active',
            'reason' => 'Identity verified with National ID.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(UserStatus::ACTIVE, $this->suspendedCustomer->fresh()->status);

        // Audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id' => $this->suspendedCustomer->id,
            'action' => 'customer_reactivated',
        ]);
    }

    public function test_marketing_manager_cannot_change_customer_status(): void
    {
        $response = $this->actingAs($this->marketingManager)->post(route('admin.customers.status', $this->customer1->id), [
            'status' => 'suspended',
            'reason' => 'Marketing manager unauthorized attempt',
        ]);

        $response->assertForbidden();
        $this->assertEquals(UserStatus::ACTIVE, $this->customer1->fresh()->status);
    }

    // ==========================================
    // 5. SESSION TERMINATION & ACCESS ENFORCEMENT
    // ==========================================

    public function test_suspending_customer_immediately_purges_their_database_sessions(): void
    {
        // Seed active sessions in sessions table
        DB::table('sessions')->insert([
            [
                'id' => 'sess_cust_1',
                'user_id' => $this->customer1->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'CustomerBrowser',
                'payload' => 'dummy',
                'last_activity' => time(),
            ],
            [
                'id' => 'sess_cust_2',
                'user_id' => $this->customer2->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'OtherCustomerBrowser',
                'payload' => 'dummy',
                'last_activity' => time(),
            ],
            [
                'id' => 'sess_owner',
                'user_id' => $this->owner->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'OwnerBrowser',
                'payload' => 'dummy',
                'last_activity' => time(),
            ],
        ]);

        // Suspend customer 1
        $this->actingAs($this->owner)->post(route('admin.customers.status', $this->customer1->id), [
            'status' => 'suspended',
            'reason' => 'Account compromised.',
        ]);

        // Customer 1's session was terminated
        $this->assertDatabaseMissing('sessions', ['id' => 'sess_cust_1']);

        // Customer 2's session remains active!
        $this->assertDatabaseHas('sessions', ['id' => 'sess_cust_2']);

        // Owner's session remains active!
        $this->assertDatabaseHas('sessions', ['id' => 'sess_owner']);
    }

    public function test_suspended_customer_is_blocked_from_customer_dashboard(): void
    {
        $response = $this->actingAs($this->suspendedCustomer)->get(route('dashboard'));
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
    }

    public function test_suspended_customer_is_blocked_from_customer_orders(): void
    {
        $response = $this->actingAs($this->suspendedCustomer)->get(route('customer.orders.index'));
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
    }

    public function test_suspended_customer_is_blocked_from_customer_addresses(): void
    {
        $response = $this->actingAs($this->suspendedCustomer)->get(route('customer.addresses.index'));
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
    }

    public function test_suspended_customer_is_blocked_from_checkout(): void
    {
        $product = Product::create([
            'name_en' => 'Test Serum',
            'name_si' => 'ටෙස්ට් සීරම්',
            'slug' => 'test-serum-checkout',
            'sku' => 'AL-SER-CHK',
            'price' => 2500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        // GET checkout blocked
        $response = $this->actingAs($this->suspendedCustomer)->get(route('checkout.index'));
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);

        // POST checkout blocked
        $response = $this->actingAs($this->suspendedCustomer)->post(route('checkout.store'), [
            'customer_name' => 'Suspended Guy',
            'customer_phone' => '0779998888',
            'shipping_address' => 'Blocked Street 12',
            'district' => 'Colombo',
            'city' => 'Colombo',
        ]);
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);

        // No order was created
        $this->assertDatabaseMissing('orders', ['customer_name' => 'Suspended Guy']);
    }

    public function test_guest_checkout_remains_operational_for_unauthenticated_users(): void
    {
        $product = Product::create([
            'name_en' => 'Guest Perfume',
            'name_si' => 'සුවඳ විලවුන්',
            'slug' => 'guest-perfume-test',
            'sku' => 'AL-GUEST-PRF',
            'price' => 3500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Legitimate Guest',
            'customer_phone' => '0770001122',
            'shipping_address' => 'Guest Lane 5',
            'district' => 'Colombo',
            'city' => 'Colombo',
        ]);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Legitimate Guest',
            'shipping_address' => 'Guest Lane 5',
        ]);
    }

    public function test_suspending_customer_rotates_remember_token_and_deletes_database_sessions(): void
    {
        $initialCustomerToken = 'initial_customer_token_1234567890';
        $otherCustomerToken = 'other_customer_token_1234567890';
        $adminToken = 'admin_token_1234567890';

        $this->customer1->forceFill(['remember_token' => $initialCustomerToken])->save();
        $this->customer2->forceFill(['remember_token' => $otherCustomerToken])->save();
        $this->superAdmin->forceFill(['remember_token' => $adminToken])->save();

        // Create sessions in DB
        DB::table('sessions')->insert([
            ['id' => 'sess_cust_1', 'user_id' => $this->customer1->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => 'payload1', 'last_activity' => time()],
            ['id' => 'sess_cust_2', 'user_id' => $this->customer2->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => 'payload2', 'last_activity' => time()],
            ['id' => 'sess_admin', 'user_id' => $this->superAdmin->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => 'payload3', 'last_activity' => time()],
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.customers.status', $this->customer1), [
            'status' => 'suspended',
            'reason' => 'Fraudulent activity detected',
        ]);

        $response->assertSessionHasNoErrors();
        $this->customer1->refresh();
        $this->customer2->refresh();
        $this->superAdmin->refresh();

        // Target customer status is suspended
        $this->assertEquals(UserStatus::SUSPENDED, $this->customer1->status);

        // Target customer remember_token was cycled (changed)
        $this->assertNotEquals($initialCustomerToken, $this->customer1->remember_token);
        $this->assertNotEmpty($this->customer1->remember_token);

        // Target customer session deleted
        $this->assertDatabaseMissing('sessions', ['id' => 'sess_cust_1']);

        // Other customer and admin tokens and sessions remain untouched
        $this->assertEquals($otherCustomerToken, $this->customer2->remember_token);
        $this->assertEquals($adminToken, $this->superAdmin->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => 'sess_cust_2']);
        $this->assertDatabaseHas('sessions', ['id' => 'sess_admin']);
    }

    public function test_suspended_customer_is_blocked_from_profile_routes(): void
    {
        // GET /profile
        $response = $this->actingAs($this->suspendedCustomer)->get(route('profile.edit'));
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);

        // PATCH /profile
        $response = $this->actingAs($this->suspendedCustomer)->patch(route('profile.update'), [
            'name' => 'Attempted Change',
            'email' => 'attempted@example.com',
        ]);
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);

        // DELETE /profile
        $response = $this->actingAs($this->suspendedCustomer)->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
    }

    public function test_suspended_customer_is_blocked_from_password_update(): void
    {
        $response = $this->actingAs($this->suspendedCustomer)->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-1234',
            'password_confirmation' => 'new-secret-1234',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
    }

    public function test_active_customer_can_access_profile_and_update_password(): void
    {
        // GET /profile succeeds
        $response = $this->actingAs($this->customer1)->get(route('profile.edit'));
        $response->assertOk();

        // PUT /password succeeds
        $response = $this->actingAs($this->customer1)->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-12345',
            'password_confirmation' => 'new-secret-12345',
        ]);

        $response->assertSessionHasNoErrors();
    }
}
