<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentInvitation;
use App\Models\AgentProfile;
use App\Models\CommissionLedgerEntry;
use App\Models\HierarchyChangeRequest;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentHierarchyService;
use App\Services\AgentInvitationService;
use App\Services\CommissionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class AgentHierarchyManagementTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;
    private AgentInvitationService $invitationService;
    private CommissionService $commissionService;

    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $accountant;
    private User $customer;
    private User $hlaAgent;
    private User $llaAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Setting::set('direct_agent_commission_percent', 25.0, 'commission', 'float');
        Setting::set('upline_agent_commission_percent', 10.0, 'commission', 'float');

        $this->hierarchyService = app(AgentHierarchyService::class);
        $this->invitationService = app(AgentInvitationService::class);
        $this->commissionService = app(CommissionService::class);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);

        $this->agentManager = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Seeded High-Level Agent (Company Direct)
        $this->hlaAgent = User::factory()->create([
            'name' => 'High Level Leader',
            'email' => 'hla.leader@example.lk',
            'phone' => '0771112222',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-HLA1',
            'parent_id' => null,
        ]);
        $this->hierarchyService->attachAgent($this->hlaAgent, null);

        // Seeded Low-Level Agent (Under HLA Leader)
        $this->llaAgent = User::factory()->create([
            'name' => 'Low Level Member',
            'email' => 'lla.member@example.lk',
            'phone' => '0773334444',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-LLA1',
            'parent_id' => $this->hlaAgent->id,
        ]);
        $this->hierarchyService->attachAgent($this->llaAgent, $this->hlaAgent);
    }

    // =========================================================================
    // A. REGISTRATION LIFECYCLE CLOSURE ATTACHMENT TESTS
    // =========================================================================

    public function test_hla_token_registration_creates_self_closure_without_parent(): void
    {
        $inviteData = $this->invitationService->createInvitation(
            $this->agentManager,
            AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            ['candidate_name' => 'New HLA Candidate', 'candidate_email' => 'candidate.hla@example.lk']
        );

        $response = $this->post(route('agent.register.store'), [
            'token' => $inviteData['token'],
            'name' => 'New HLA Candidate',
            'email' => 'candidate.hla@example.lk',
            'phone' => '0775551111',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199212345678',
            'address' => '123 Galle Rd, Colombo',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Bambalapitiya',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'New HLA Candidate',
        ]);

        $response->assertRedirect(route('login'));

        $registeredUser = User::where('email', 'candidate.hla@example.lk')->first();
        $this->assertNotNull($registeredUser);
        $this->assertEquals(UserRole::HIGH_LEVEL_AGENT, $registeredUser->role);
        $this->assertNull($registeredUser->parent_id);

        // Assert self-closure exists at depth 0
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $registeredUser->id,
            'descendant_id' => $registeredUser->id,
            'depth' => 0,
        ]);

        // Assert no upline closures exist
        $this->assertEquals(
            1,
            UserClosure::where('descendant_id', $registeredUser->id)->count()
        );
    }

    public function test_lla_token_registration_creates_self_and_ancestor_closures(): void
    {
        $inviteData = $this->invitationService->createInvitation(
            $this->hlaAgent,
            AgentInvitation::TYPE_LOW_LEVEL_AGENT,
            ['candidate_name' => 'New LLA Candidate', 'candidate_email' => 'candidate.lla@example.lk']
        );

        $response = $this->post(route('agent.register.store'), [
            'token' => $inviteData['token'],
            'name' => 'New LLA Candidate',
            'email' => 'candidate.lla@example.lk',
            'phone' => '0775552222',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199412345678',
            'address' => '456 Kandy Rd, Kelaniya',
            'bank_name' => 'Sampath Bank',
            'bank_branch' => 'Kelaniya',
            'bank_account_number' => '9876543210',
            'bank_account_name' => 'New LLA Candidate',
        ]);

        $response->assertRedirect(route('login'));

        $registeredUser = User::where('email', 'candidate.lla@example.lk')->first();
        $this->assertNotNull($registeredUser);
        $this->assertEquals(UserRole::LOW_LEVEL_AGENT, $registeredUser->role);
        $this->assertEquals($this->hlaAgent->id, $registeredUser->parent_id);

        // Assert self-closure exists at depth 0
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $registeredUser->id,
            'descendant_id' => $registeredUser->id,
            'depth' => 0,
        ]);

        // Assert parent ancestor closure exists at depth 1
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $this->hlaAgent->id,
            'descendant_id' => $registeredUser->id,
            'depth' => 1,
        ]);
    }

    public function test_referral_based_lla_registration_resolves_sponsor_and_creates_closures(): void
    {
        $response = $this->post(route('agent.register.store'), [
            'referral_code' => 'AGT-HLA1',
            'name' => 'Referral Candidate',
            'email' => 'candidate.ref@example.lk',
            'phone' => '0775553333',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199512345678',
            'address' => '789 High Level Rd, Nugegoda',
            'bank_name' => 'Bank of Ceylon',
            'bank_branch' => 'Nugegoda',
            'bank_account_number' => '4567891230',
            'bank_account_name' => 'Referral Candidate',
        ]);

        $response->assertRedirect(route('login'));

        $registeredUser = User::where('email', 'candidate.ref@example.lk')->first();
        $this->assertNotNull($registeredUser);
        $this->assertEquals(UserRole::LOW_LEVEL_AGENT, $registeredUser->role);
        $this->assertEquals($this->hlaAgent->id, $registeredUser->parent_id);

        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $registeredUser->id,
            'descendant_id' => $registeredUser->id,
            'depth' => 0,
        ]);

        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $this->hlaAgent->id,
            'descendant_id' => $registeredUser->id,
            'depth' => 1,
        ]);
    }

    public function test_failed_registration_rolls_back_user_and_closures_atomically(): void
    {
        // Missing mandatory bank_account_number will fail validation
        $response = $this->post(route('agent.register.store'), [
            'referral_code' => 'AGT-HLA1',
            'name' => 'Failing Candidate',
            'email' => 'failing.candidate@example.lk',
            'phone' => '0775554444',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199612345678',
            'address' => 'No 1, Temple Rd',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            // Missing bank_account_number & bank_account_name
        ]);

        $response->assertSessionHasErrors(['bank_account_number', 'bank_account_name']);

        $this->assertDatabaseMissing('users', ['email' => 'failing.candidate@example.lk']);
    }

    // =========================================================================
    // B. AGENT ACTIVATION CLOSURE FALLBACK TESTS
    // =========================================================================

    public function test_admin_activation_attaches_missing_closures_safely(): void
    {
        // Create an agent who artificially lacks closure records (simulating legacy gap)
        $legacyAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
            'parent_id' => $this->hlaAgent->id,
        ]);

        // Verify zero closures exist for legacyAgent
        $this->assertDatabaseMissing('user_closures', [
            'ancestor_id' => $legacyAgent->id,
            'descendant_id' => $legacyAgent->id,
        ]);

        // Agent Manager activates agent
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.status.update', $legacyAgent->id),
            ['status' => 'active', 'reason' => 'Application approved']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $legacyAgent->refresh();
        $this->assertEquals(UserStatus::ACTIVE, $legacyAgent->status);

        // Verify closures were automatically healed
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $legacyAgent->id,
            'descendant_id' => $legacyAgent->id,
            'depth' => 0,
        ]);
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $this->hlaAgent->id,
            'descendant_id' => $legacyAgent->id,
            'depth' => 1,
        ]);
    }

    // =========================================================================
    // C. COMMISSION UPLINE RESOLUTION SAFEGUARD TESTS
    // =========================================================================

    public function test_commission_upline_fallback_resolves_hla_when_closure_records_are_missing(): void
    {
        // Create an active LLA agent with parent_id = HLA, but explicitly delete closure records
        $orphanClosureAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $this->hlaAgent->id,
            'referral_code' => 'AGT-ORPHAN1',
        ]);
        AgentProfile::create([
            'user_id' => $orphanClosureAgent->id,
            'wallet_balance' => 0.00,
            'total_earned' => 0.00,
            'total_withdrawn' => 0.00,
        ]);

        // Deliberately delete any closure links for orphanClosureAgent
        UserClosure::where('descendant_id', $orphanClosureAgent->id)->delete();
        $this->assertDatabaseMissing('user_closures', ['descendant_id' => $orphanClosureAgent->id]);

        // Now place an order attributed to orphanClosureAgent
        $order = Order::create([
            'order_number' => 'ORD-TEST-ORPHAN',
            'agent_id' => $orphanClosureAgent->id,
            'referral_code' => $orphanClosureAgent->referral_code,
            'customer_name' => 'Test Customer',
            'customer_phone' => '0779998888',
            'shipping_address' => 'Colombo',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'tracking_token' => 'TRACK-ORPHAN-123',
            'subtotal' => 10000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 10350.00,
            'payment_method' => 'cod',
            'status' => 'placed',
        ]);

        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        // Assert 2 commission entries created: 25% direct LLA (2500) and 10% upline HLA (1000)
        $this->assertCount(2, $entries);

        $directEntry = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_DIRECT_LOW_LEVEL);
        $this->assertNotNull($directEntry);
        $this->assertEquals(2500.00, (float) $directEntry->calculated_amount);

        $uplineEntry = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE);
        $this->assertNotNull($uplineEntry);
        $this->assertEquals(1000.00, (float) $uplineEntry->calculated_amount);
        $this->assertEquals($this->hlaAgent->id, $uplineEntry->agent_id);

        // Assert NO writes occurred to user_closures table during commission calculation
        $this->assertDatabaseMissing('user_closures', ['descendant_id' => $orphanClosureAgent->id]);
    }

    public function test_cycle_detection_in_fallback_upline_traversal_prevents_infinite_loop(): void
    {
        // Construct a circular parent_id loop: Agent A -> Agent B -> Agent A
        $agentA = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        $agentB = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);

        $agentA->update(['parent_id' => $agentB->id]);
        $agentB->update(['parent_id' => $agentA->id]);

        // Delete closures to force fallback traversal
        UserClosure::whereIn('descendant_id', [$agentA->id, $agentB->id])->delete();

        // Fallback traversal should break cleanly without throwing infinite loop or memory exhaustion
        $upline = $this->hierarchyService->getUpline($agentA);
        $this->assertNotNull($upline);
        $this->assertLessThanOrEqual(2, $upline->count());
    }

    // =========================================================================
    // D. HIERARCHY MOVE CONCURRENCY & VALIDATION TESTS
    // =========================================================================

    public function test_self_parenting_is_strictly_blocked_in_move_agent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An agent cannot be their own parent.');

        $this->hierarchyService->moveAgent($this->llaAgent, $this->llaAgent);
    }

    public function test_circular_move_is_blocked_by_cycle_detection(): void
    {
        // Child LLA has sub-agent
        $subLla = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $this->llaAgent->id,
        ]);
        $this->hierarchyService->attachAgent($subLla, $this->llaAgent);

        // Attempting to move HLA under subLla must throw cycle detection
        $this->expectException(InvalidArgumentException::class);

        $this->hierarchyService->moveAgent($this->hlaAgent, $subLla);
    }

    public function test_hla_cannot_be_moved_under_another_sponsor(): void
    {
        $otherHla = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => null,
        ]);
        $this->hierarchyService->attachAgent($otherHla, null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A High-Level Agent must remain company-direct and cannot be placed under a sponsor.');

        $this->hierarchyService->moveAgent($this->hlaAgent, $otherHla);
    }

    public function test_customer_cannot_be_assigned_as_new_parent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('New parent must be an active agent.');

        $this->hierarchyService->moveAgent($this->llaAgent, $this->customer);
    }

    // =========================================================================
    // E. ROUTE ACCESSIBILITY & AUTHORIZATION TESTS
    // =========================================================================

    public function test_agent_manager_can_access_network_tree_explorer(): void
    {
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy'));
        $response->assertStatus(200);
    }

    public function test_agent_manager_can_access_hierarchy_requests_queue(): void
    {
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy-requests'));
        $response->assertStatus(200);
    }

    public function test_hierarchy_nodes_api_endpoint_returns_children_json(): void
    {
        $response = $this->actingAs($this->agentManager)->get(
            route('admin.agents.hierarchy.nodes', ['parent_id' => $this->hlaAgent->id])
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'parent_id',
            'nodes' => [
                '*' => ['id', 'name', 'role', 'children_count'],
            ],
        ]);
        $this->assertCount(1, $response->json('nodes'));
        $this->assertEquals($this->llaAgent->id, $response->json('nodes.0.id'));
    }

    public function test_agent_manager_can_submit_hierarchy_change_request(): void
    {
        $newHla = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => null,
        ]);
        $this->hierarchyService->attachAgent($newHla, null);

        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy.request', $this->llaAgent->id),
            [
                'requested_parent_id' => $newHla->id,
                'reason' => 'Territory realignment in southern province.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('hierarchy_change_requests', [
            'agent_id' => $this->llaAgent->id,
            'current_parent_id' => $this->hlaAgent->id,
            'requested_parent_id' => $newHla->id,
            'requested_by' => $this->agentManager->id,
            'status' => 'pending',
        ]);
    }

    public function test_duplicate_pending_hierarchy_request_is_rejected(): void
    {
        HierarchyChangeRequest::create([
            'agent_id' => $this->llaAgent->id,
            'current_parent_id' => $this->hlaAgent->id,
            'requested_parent_id' => null,
            'requested_by' => $this->agentManager->id,
            'reason' => 'First pending request',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy.request', $this->llaAgent->id),
            [
                'requested_parent_id' => null,
                'reason' => 'Second duplicate request',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_non_owner_cannot_approve_hierarchy_change_request(): void
    {
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->llaAgent->id,
            'current_parent_id' => $this->hlaAgent->id,
            'requested_parent_id' => null,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Promotion test',
            'status' => 'pending',
        ]);

        // Agent Manager cannot approve (403)
        $this->actingAs($this->agentManager)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Super Admin cannot approve (403)
        $this->actingAs($this->superAdmin)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Accountant cannot approve (403)
        $this->actingAs($this->accountant)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Request remains pending
        $changeRequest->refresh();
        $this->assertEquals('pending', $changeRequest->status);
    }

    public function test_owner_can_approve_hierarchy_change_request_and_executes_movement(): void
    {
        $newHla = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => null,
        ]);
        $this->hierarchyService->attachAgent($newHla, null);

        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->llaAgent->id,
            'current_parent_id' => $this->hlaAgent->id,
            'requested_parent_id' => $newHla->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Promotion test',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'approve', 'review_notes' => 'Executive approval granted']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $changeRequest->refresh();
        $this->assertEquals('approved', $changeRequest->status);
        $this->assertEquals($this->owner->id, $changeRequest->reviewed_by);

        // Verify llaAgent parent updated
        $this->llaAgent->refresh();
        $this->assertEquals($newHla->id, $this->llaAgent->parent_id);

        // Verify closure moved from old HLA to new HLA
        $this->assertDatabaseMissing('user_closures', [
            'ancestor_id' => $this->hlaAgent->id,
            'descendant_id' => $this->llaAgent->id,
        ]);
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $newHla->id,
            'descendant_id' => $this->llaAgent->id,
            'depth' => 1,
        ]);
    }

    public function test_owner_can_reject_hierarchy_change_request_with_notes(): void
    {
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->llaAgent->id,
            'current_parent_id' => $this->hlaAgent->id,
            'requested_parent_id' => null,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Promotion test',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'reject', 'review_notes' => 'Sales targets not yet achieved.']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $changeRequest->refresh();
        $this->assertEquals('rejected', $changeRequest->status);
        $this->assertEquals('Sales targets not yet achieved.', $changeRequest->review_notes);

        // Parent unchanged
        $this->llaAgent->refresh();
        $this->assertEquals($this->hlaAgent->id, $this->llaAgent->parent_id);
    }
}
