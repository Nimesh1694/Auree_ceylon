<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Category;
use App\Models\HierarchyChangeRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentHierarchyService;
use App\Services\OrderStateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;
use Tests\TestCase;

class Phase4SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;
    private User $owner;
    private User $itManager;
    private User $marketingManager;
    private User $accountant;
    private User $activeTopAgent;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');
        $this->hierarchyService = app(AgentHierarchyService::class);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->itManager = User::factory()->create([
            'role' => UserRole::IT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->marketingManager = User::factory()->create([
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->activeTopAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'TOP-AGT1',
        ]);
        $this->hierarchyService->attachAgent($this->activeTopAgent, null);
    }

    // =========================================================================
    // 1. REGISTRATION & APPROVAL SECURITY AUDIT
    // =========================================================================

    public function test_registration_with_suspended_sponsor_code_is_rejected(): void
    {
        $suspendedAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::SUSPENDED,
            'referral_code' => 'SUSP-AGT1',
        ]);

        $response = $this->post(route('agent.register.store'), [
            'name' => 'Alice Perera',
            'email' => 'alice@example.com',
            'phone' => '0771239999',
            'password' => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
            'referral_code' => 'SUSP-AGT1',
            'nic' => '199511119999',
            'address' => 'No 1, Galle Road, Colombo',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1122334455',
            'bank_account_name' => 'Alice Perera',
        ]);

        $response->assertSessionHasErrors(['referral_code']);
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
    }

    public function test_registration_with_customer_referral_code_is_rejected(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'CUST-REF1',
        ]);

        $response = $this->post(route('agent.register.store'), [
            'name' => 'Bob Silva',
            'email' => 'bob@example.com',
            'phone' => '0772228888',
            'password' => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
            'referral_code' => 'CUST-REF1',
            'nic' => '199522228888',
            'address' => 'No 2, Kandy Road, Kiribathgoda',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Kiribathgoda',
            'bank_account_number' => '9988776655',
            'bank_account_name' => 'Bob Silva',
        ]);

        $response->assertSessionHasErrors(['referral_code']);
        $this->assertDatabaseMissing('users', ['email' => 'bob@example.com']);
    }

    public function test_registration_payload_tampering_is_prevented(): void
    {
        $response = $this->post(route('agent.register.store'), [
            'name' => 'Attacker Agent',
            'email' => 'attacker@example.com',
            'phone' => '0773337777',
            'password' => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
            'referral_code' => 'TOP-AGT1',
            'nic' => '199533337777',
            'address' => 'No 3, High Level Road, Nugegoda',
            'bank_name' => 'Sampath Bank',
            'bank_branch' => 'Nugegoda',
            'bank_account_number' => '3344556677',
            'bank_account_name' => 'Attacker Agent',
            // Malicious payload attempts:
            'role' => 'owner',
            'status' => 'active',
            'wallet_balance' => 999999,
            'kyc_status' => 'verified',
        ]);

        $response->assertRedirect(route('login'));
        $createdAgent = User::where('email', 'attacker@example.com')->first();
        $this->assertNotNull($createdAgent);

        // Verify hardcoded low_level_agent and pending status
        $this->assertEquals(UserRole::LOW_LEVEL_AGENT, $createdAgent->role);
        $this->assertEquals(UserStatus::PENDING, $createdAgent->status);

        $profile = $createdAgent->agentProfile;
        $this->assertNotNull($profile);
        $this->assertEquals('pending', $profile->kyc_status);
        $this->assertEquals(0.00, (float) $profile->wallet_balance);
    }

    public function test_pending_agent_is_strictly_barred_from_agent_portal(): void
    {
        $pendingAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
        ]);

        $this->actingAs($pendingAgent)->get(route('agent.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($pendingAgent)->get(route('agent.profile'))->assertRedirect(route('login'));
        $this->actingAs($pendingAgent)->get(route('agent.orders.index'))->assertRedirect(route('login'));
        $this->actingAs($pendingAgent)->get(route('agent.orders.create'))->assertRedirect(route('login'));
        $this->actingAs($pendingAgent)->get(route('agent.team'))->assertRedirect(route('login'));
    }

    // =========================================================================
    // 2. REFERRAL ATTRIBUTION & TAMPER-PROOFING AUDIT
    // =========================================================================

    public function test_visiting_home_with_valid_active_agent_code_captures_referral_correctly(): void
    {
        $response = $this->get('/?ref=TOP-AGT1');
        $response->assertStatus(200);
        $response->assertSessionHas('referral_code', 'TOP-AGT1');
        $response->assertCookie('referral_code', 'TOP-AGT1');
    }

    public function test_visiting_home_with_invalid_code_does_not_store_referral_in_session_or_cookie(): void
    {
        $response = $this->get('/?ref=NONEXISTENT_CODE_999');
        $response->assertStatus(200);
        $response->assertSessionMissing('referral_code');
        $response->assertCookieMissing('referral_code');
    }

    public function test_visiting_home_with_suspended_agent_code_does_not_capture_referral(): void
    {
        $suspendedAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::SUSPENDED,
            'referral_code' => 'SUSP-REF-99',
        ]);

        $response = $this->get('/?ref=SUSP-REF-99');
        $response->assertStatus(200);
        $response->assertSessionMissing('referral_code');
        $response->assertCookieMissing('referral_code');
    }

    public function test_invalid_referral_cannot_overwrite_existing_valid_referral(): void
    {
        // 1. Visit with valid active agent
        $response1 = $this->get('/?ref=TOP-AGT1');
        $response1->assertSessionHas('referral_code', 'TOP-AGT1');

        // 2. Visit again with invalid referral query
        $response2 = $this->withSession(['referral_code' => 'TOP-AGT1'])->get('/?ref=INVALID_FAKE_CODE');
        $response2->assertStatus(200);

        // Pre-existing valid referral code is preserved and not corrupted
        $this->assertEquals('TOP-AGT1', session('referral_code'));
    }

    public function test_existing_valid_referral_attribution_continues_working_across_storefront_routes(): void
    {
        // Visit category or product page with valid referral
        $response = $this->get('/products?ref=TOP-AGT1');
        $response->assertStatus(200);
        $response->assertSessionHas('referral_code', 'TOP-AGT1');
        $response->assertCookie('referral_code', 'TOP-AGT1');
    }

    public function test_orders_without_valid_referral_attribution_remain_unattributed(): void
    {
        $category = Category::create([
            'name_en' => 'Organic Line',
            'name_si' => 'කාබනික',
            'slug' => 'organic-line',
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Pure Rose Mist',
            'name_si' => 'රෝස මිස්ට්',
            'slug' => 'pure-rose-mist',
            'sku' => 'MIST-ROSE-01',
            'price' => 1800.00,
            'stock_quantity' => 15,
            'low_stock_threshold' => 2,
            'is_active' => true,
            'is_featured' => true,
            'is_best_seller' => true,
            'is_new_arrival' => false,
        ]);

        // Place direct customer order with no referral session
        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Organic Shopper',
            'customer_phone' => '0776667777',
            'shipping_address' => 'No 88, Galle Road, Moratuwa',
            'district' => 'Colombo',
            'city' => 'Moratuwa',
        ]);
        $response->assertSessionDoesntHaveErrors();

        $order = Order::where('customer_name', 'Organic Shopper')->first();
        $this->assertNotNull($order);
        $this->assertNull($order->referral_code);
        $this->assertNull($order->agent_id);
    }

    public function test_checkout_cannot_be_injected_with_arbitrary_referral_code(): void
    {
        $category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සම රැකවරණය',
            'slug' => 'skincare-audit',
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Audit Night Cream',
            'name_si' => 'ක්‍රීම්',
            'slug' => 'audit-night-cream',
            'sku' => 'SKIN-AUDIT-01',
            'price' => 3000.00,
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
            'is_featured' => true,
            'is_best_seller' => true,
            'is_new_arrival' => false,
        ]);

        // Put product in session cart without visiting any referral link
        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1]);

        // Attempt to pass malicious referral_code in POST /checkout payload
        $checkoutData = [
            'customer_name' => 'Charlie Test',
            'customer_phone' => '0774445555',
            'customer_email' => 'charlie@example.com',
            'shipping_address' => 'No 45, Sea Street, Colombo',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'referral_code' => 'INJECTED_CODE_999', // Malicious attempt to claim attribution
        ];

        $response = $this->post(route('checkout.store'), $checkoutData);
        $response->assertSessionDoesntHaveErrors();

        $order = Order::where('customer_name', 'Charlie Test')->first();
        $this->assertNotNull($order);

        // Referral code must remain null because no valid referral session was set
        $this->assertNull($order->referral_code);
        $this->assertNull($order->agent_id);
    }

    public function test_separate_order_attribution_between_placing_agent_and_referral(): void
    {
        $category = Category::create([
            'name_en' => 'Body Care',
            'name_si' => 'ශරීර සත්කාර',
            'slug' => 'body-care-audit',
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Audit Body Lotion',
            'name_si' => 'ලෝෂන්',
            'slug' => 'audit-body-lotion',
            'sku' => 'BODY-AUDIT-01',
            'price' => 2500.00,
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
            'is_featured' => true,
            'is_best_seller' => true,
            'is_new_arrival' => false,
        ]);

        $agent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'FIELD-AGENT-X',
        ]);
        AgentProfile::create([
            'user_id' => $agent->id,
            'nic' => '199244445555',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $orderData = [
            'customer_name' => 'Offline Client',
            'customer_phone' => '0775556666',
            'shipping_address' => 'No 10, Main Street, Negombo',
            'district' => 'Gampaha',
            'city' => 'Negombo',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($agent)->post(route('agent.orders.store'), $orderData);
        $response->assertRedirect(route('agent.orders.index'));

        $order = Order::where('customer_name', 'Offline Client')->first();
        $this->assertNotNull($order);

        // orders.agent_id identifies the placing agent
        $this->assertEquals($agent->id, $order->agent_id);
        // orders.referral_code records the marketing credit
        $this->assertEquals('FIELD-AGENT-X', $order->referral_code);
    }

    // =========================================================================
    // 3. AGENT HIERARCHY INTEGRITY & CYCLE DETECTION AUDIT
    // =========================================================================

    public function test_cycle_detection_blocks_agent_from_becoming_child_of_descendant(): void
    {
        $agentA = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        $agentB = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        $agentC = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);

        // Tree: A -> B -> C
        $this->hierarchyService->attachAgent($agentA, null);
        $this->hierarchyService->attachAgent($agentB, $agentA);
        $this->hierarchyService->attachAgent($agentC, $agentB);

        // Attempt to move A under C (illegal cycle!)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot move an agent under one of their own descendants (cycle detected).');

        $this->hierarchyService->moveAgent($agentA, $agentC);
    }

    public function test_self_parent_is_strictly_blocked(): void
    {
        $agentA = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        $this->hierarchyService->attachAgent($agentA, null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An agent cannot be their own parent.');

        $this->hierarchyService->moveAgent($agentA, $agentA);
    }

    public function test_non_owner_managers_cannot_approve_hierarchy_move_requests(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        $newParent = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        $this->hierarchyService->attachAgent($applicant, null);
        $this->hierarchyService->attachAgent($newParent, null);

        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $applicant->id,
            'current_parent_id' => null,
            'requested_parent_id' => $newParent->id,
            'requested_by' => $this->itManager->id,
            'reason' => 'Audit move test',
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        // IT Manager attempt -> 403
        $this->actingAs($this->itManager)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Marketing Manager attempt -> 403
        $this->actingAs($this->marketingManager)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Accountant attempt -> 403
        $this->actingAs($this->accountant)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Change request remains pending
        $changeRequest->refresh();
        $this->assertEquals(HierarchyChangeRequest::STATUS_PENDING, $changeRequest->status);
    }

    // =========================================================================
    // 4. AGENT PRIVACY & DATA ISOLATION AUDIT
    // =========================================================================

    public function test_downline_network_api_strictly_masks_sensitive_records(): void
    {
        $parentAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'SPONSOR-PRIV-1',
        ]);
        $childAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $parentAgent->id,
            'referral_code' => 'CHILD-PRIV-1',
        ]);

        AgentProfile::create([
            'user_id' => $childAgent->id,
            'nic' => '199888889999',
            'bank_name' => 'Secret National Bank',
            'bank_branch' => 'Head Office',
            'bank_account_number' => 'SECRET-ACC-999',
            'bank_account_name' => 'Child Agent Legal Name',
            'wallet_balance' => 75000.00,
            'total_earned' => 80000.00,
            'total_withdrawn' => 5000.00,
            'kyc_status' => 'verified',
            'kyc_notes' => 'Confidential KYC compliance note',
        ]);

        $this->hierarchyService->attachAgent($parentAgent, null);
        $this->hierarchyService->attachAgent($childAgent, $parentAgent);

        $response = $this->actingAs($parentAgent)->get(route('agent.team'));
        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agent/Team')
            ->has('downline', 1, fn (AssertableInertia $downline) => $downline
                ->where('id', $childAgent->id)
                ->where('name', $childAgent->name)
                ->where('referral_code', 'CHILD-PRIV-1')
                ->where('depth', 1)
                // Assert sensitive fields are completely absent from downline payload
                ->missing('nic')
                ->missing('bank_name')
                ->missing('bank_branch')
                ->missing('bank_account_number')
                ->missing('bank_account_name')
                ->missing('wallet_balance')
                ->missing('total_earned')
                ->missing('total_withdrawn')
                ->missing('kyc_status')
                ->missing('kyc_notes')
                ->etc()
            )
        );
    }

    public function test_agent_cannot_view_orders_placed_by_other_agents(): void
    {
        $agentA = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        $agentB = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);

        $orderA = Order::create([
            'order_number' => 'ORD-AUDIT-A',
            'agent_id' => $agentA->id,
            'referral_code' => $agentA->referral_code,
            'customer_name' => 'Customer A',
            'customer_phone' => '0771110000',
            'shipping_address' => 'Addr A',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 2000,
            'delivery_charge' => 350,
            'total_amount' => 2350,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'TOKEN_A',
            'placed_at' => now(),
        ]);

        $orderB = Order::create([
            'order_number' => 'ORD-AUDIT-B',
            'agent_id' => $agentB->id,
            'referral_code' => $agentB->referral_code,
            'customer_name' => 'Customer B',
            'customer_phone' => '0772220000',
            'shipping_address' => 'Addr B',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 3000,
            'delivery_charge' => 350,
            'total_amount' => 3350,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'TOKEN_B',
            'placed_at' => now(),
        ]);

        $response = $this->actingAs($agentA)->get(route('agent.orders.index'));
        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agent/Orders/Index')
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-AUDIT-A')
        );
    }

    // =========================================================================
    // 5. MANAGEMENT AUTHORIZATION & RBAC AUDIT
    // =========================================================================

    public function test_unauthorized_management_roles_cannot_approve_agent_applications(): void
    {
        $applicant = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
        ]);

        // Marketing Manager attempt -> 403
        $this->actingAs($this->marketingManager)
            ->post(route('admin.agents.approve', $applicant->id))
            ->assertStatus(403);

        // Accountant attempt -> 403
        $this->actingAs($this->accountant)
            ->post(route('admin.agents.approve', $applicant->id))
            ->assertStatus(403);

        $applicant->refresh();
        $this->assertEquals(UserStatus::PENDING, $applicant->status);
    }

    public function test_regular_agent_cannot_access_admin_agent_management_routes(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($agent)->get(route('admin.agents.index'))->assertStatus(403);
        $this->actingAs($agent)->get(route('admin.agents.show', $agent->id))->assertStatus(403);
        $this->actingAs($agent)->post(route('admin.agents.approve', $agent->id))->assertStatus(403);
        $this->actingAs($agent)->post(route('admin.agents.reject', $agent->id))->assertStatus(403);
    }
}
