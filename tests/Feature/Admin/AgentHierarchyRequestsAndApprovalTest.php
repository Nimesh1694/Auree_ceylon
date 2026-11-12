<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\HierarchyChangeRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentHierarchyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentHierarchyRequestsAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;

    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $itManager;
    private User $marketingManager;
    private User $customer;
    private User $hla1;
    private User $hla2;
    private User $lla1;
    private User $childLla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->hierarchyService = app(AgentHierarchyService::class);

        // System users
        $this->owner = User::factory()->create([
            'name' => 'Executive Owner',
            'email' => 'owner@auraluxe.lk',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@auraluxe.lk',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);

        $this->agentManager = User::factory()->create([
            'name' => 'Agent Network Manager',
            'email' => 'agentmgr@auraluxe.lk',
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->itManager = User::factory()->create([
            'name' => 'IT Operations Manager',
            'email' => 'itops@auraluxe.lk',
            'role' => UserRole::IT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->marketingManager = User::factory()->create([
            'name' => 'Marketing Manager',
            'email' => 'marketing@auraluxe.lk',
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Retail Customer',
            'email' => 'customer@example.lk',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Agents: HLA1 -> LLA1 -> ChildLLA
        $this->hla1 = User::factory()->create([
            'name' => 'HLA Leader 1',
            'email' => 'hla1@example.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-HLA-001',
            'parent_id' => null,
        ]);
        AgentProfile::create([
            'user_id' => $this->hla1->id,
            'nic' => '198511223344',
            'kyc_status' => 'verified',
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '1112223334',
            'wallet_balance' => 45000.00,
        ]);
        $this->hierarchyService->attachAgent($this->hla1, null);

        $this->hla2 = User::factory()->create([
            'name' => 'HLA Leader 2',
            'email' => 'hla2@example.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-HLA-002',
            'parent_id' => null,
        ]);
        AgentProfile::create([
            'user_id' => $this->hla2->id,
            'nic' => '198855667788',
            'kyc_status' => 'verified',
            'bank_name' => 'Sampath Bank',
            'bank_account_number' => '5556667778',
            'wallet_balance' => 30000.00,
        ]);
        $this->hierarchyService->attachAgent($this->hla2, null);

        $this->lla1 = User::factory()->create([
            'name' => 'LLA Member 1',
            'email' => 'lla1@example.lk',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-LLA-001',
            'parent_id' => $this->hla1->id,
        ]);
        AgentProfile::create([
            'user_id' => $this->lla1->id,
            'nic' => '199599887766',
            'kyc_status' => 'verified',
            'bank_name' => 'HNB',
            'bank_account_number' => '9998887776',
            'wallet_balance' => 15000.00,
        ]);
        $this->hierarchyService->attachAgent($this->lla1, $this->hla1);

        $this->childLla = User::factory()->create([
            'name' => 'Child LLA Member',
            'email' => 'child.lla@example.lk',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-LLA-002',
            'parent_id' => $this->lla1->id,
        ]);
        AgentProfile::create([
            'user_id' => $this->childLla->id,
            'nic' => '199844332211',
            'kyc_status' => 'verified',
            'bank_name' => 'BOC',
            'bank_account_number' => '4443332221',
            'wallet_balance' => 5000.00,
        ]);
        $this->hierarchyService->attachAgent($this->childLla, $this->lla1);
    }

    // =========================================================================
    // 1. REQUEST CREATION & VALIDATION TESTS
    // =========================================================================

    public function test_agent_manager_can_submit_hierarchy_change_request_via_store_route(): void
    {
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy-requests.store'),
            [
                'agent_id' => $this->childLla->id,
                'requested_parent_id' => $this->hla2->id,
                'reason' => 'Territorial franchise transfer to Western Province team.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('hierarchy_change_requests', [
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'status' => HierarchyChangeRequest::STATUS_PENDING,
            'reason' => 'Territorial franchise transfer to Western Province team.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'hierarchy_move_requested',
        ]);
    }

    public function test_reason_is_mandatory_for_hierarchy_move_request(): void
    {
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy-requests.store'),
            [
                'agent_id' => $this->childLla->id,
                'requested_parent_id' => $this->hla2->id,
                'reason' => '',
            ]
        );

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_target_user_must_be_an_agent(): void
    {
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy-requests.store'),
            [
                'agent_id' => $this->customer->id,
                'requested_parent_id' => $this->hla1->id,
                'reason' => 'Attempting to move a customer into agent hierarchy.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_proposed_sponsor_must_be_active_agent(): void
    {
        // Suspended agent as sponsor
        $suspendedAgent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::SUSPENDED,
        ]);

        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy-requests.store'),
            [
                'agent_id' => $this->childLla->id,
                'requested_parent_id' => $suspendedAgent->id,
                'reason' => 'Transferring to suspended sponsor.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_hla_must_remain_company_direct_and_cannot_be_sponsored(): void
    {
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy-requests.store'),
            [
                'agent_id' => $this->hla1->id,
                'requested_parent_id' => $this->hla2->id,
                'reason' => 'Attempting to place HLA under another HLA.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_self_sponsorship_is_strictly_rejected(): void
    {
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy-requests.store'),
            [
                'agent_id' => $this->childLla->id,
                'requested_parent_id' => $this->childLla->id,
                'reason' => 'Agent attempting to sponsor themselves.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_redundant_move_under_same_current_parent_is_rejected(): void
    {
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy-requests.store'),
            [
                'agent_id' => $this->childLla->id,
                'requested_parent_id' => $this->lla1->id, // Already child of lla1
                'reason' => 'Redundant move request under same parent.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_cycle_move_at_submission_is_rejected(): void
    {
        // Moving lla1 under childLla (its own descendant)
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy-requests.store'),
            [
                'agent_id' => $this->lla1->id,
                'requested_parent_id' => $this->childLla->id,
                'reason' => 'Cycle movement attempt.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_duplicate_pending_request_for_same_agent_is_rejected(): void
    {
        HierarchyChangeRequest::create([
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'First pending move request.',
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy-requests.store'),
            [
                'agent_id' => $this->childLla->id,
                'requested_parent_id' => $this->hla1->id,
                'reason' => 'Duplicate pending move request attempt.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // =========================================================================
    // 2. APPROVAL & REJECTION AUTHORIZATION BOUNDARIES
    // =========================================================================

    public function test_non_owners_cannot_approve_or_reject_requests(): void
    {
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Pending review attempt test.',
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        // Agent Manager -> 403
        $this->actingAs($this->agentManager)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Super Admin -> 403
        $this->actingAs($this->superAdmin)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // IT Manager -> 403
        $this->actingAs($this->itManager)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Marketing Manager -> 403
        $this->actingAs($this->marketingManager)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Customer -> 403
        $this->actingAs($this->customer)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // State remains pending
        $changeRequest->refresh();
        $this->assertEquals(HierarchyChangeRequest::STATUS_PENDING, $changeRequest->status);
    }

    public function test_owner_can_approve_request_and_executes_hierarchy_movement(): void
    {
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Strategic territory realignment.',
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            [
                'action' => 'approve',
                'review_notes' => 'Executive Owner approval granted for territory transfer.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $changeRequest->refresh();
        $this->assertEquals(HierarchyChangeRequest::STATUS_APPROVED, $changeRequest->status);
        $this->assertEquals($this->owner->id, $changeRequest->reviewed_by);
        $this->assertNotNull($changeRequest->reviewed_at);
        $this->assertEquals('Executive Owner approval granted for territory transfer.', $changeRequest->review_notes);

        // Verify agent parent updated in database
        $this->childLla->refresh();
        $this->assertEquals($this->hla2->id, $this->childLla->parent_id);

        // Verify closure records updated
        $this->assertDatabaseMissing('user_closures', [
            'ancestor_id' => $this->lla1->id,
            'descendant_id' => $this->childLla->id,
        ]);
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $this->hla2->id,
            'descendant_id' => $this->childLla->id,
            'depth' => 1,
        ]);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'hierarchy_move_approved',
        ]);
    }

    public function test_owner_can_reject_request_with_decision_notes(): void
    {
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Strategic realignment.',
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            [
                'action' => 'reject',
                'review_notes' => 'Current regional team still requires agent contribution.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $changeRequest->refresh();
        $this->assertEquals(HierarchyChangeRequest::STATUS_REJECTED, $changeRequest->status);
        $this->assertEquals($this->owner->id, $changeRequest->reviewed_by);
        $this->assertEquals('Current regional team still requires agent contribution.', $changeRequest->review_notes);

        // Parent must remain unchanged
        $this->childLla->refresh();
        $this->assertEquals($this->lla1->id, $this->childLla->parent_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'hierarchy_move_rejected',
        ]);
    }

    // =========================================================================
    // 3. CONCURRENCY, STALE REQUEST & ATOMICITY TESTS
    // =========================================================================

    public function test_stale_request_is_detected_and_rejected_when_parent_changed_before_approval(): void
    {
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Transfer before out-of-band change.',
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        // Out of band change: childLla parent was moved to hla1 directly
        $this->childLla->update(['parent_id' => $this->hla1->id]);

        $response = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'approve']
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $changeRequest->refresh();
        $this->assertEquals(HierarchyChangeRequest::STATUS_REJECTED, $changeRequest->status);
        $this->assertStringContainsString('Stale request', (string) $changeRequest->review_notes);

        // Target agent parent remains hla1 (not moved to hla2)
        $this->childLla->refresh();
        $this->assertEquals($this->hla1->id, $this->childLla->parent_id);
    }

    public function test_duplicate_or_replay_approval_is_prevented(): void
    {
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Replay test.',
            'status' => HierarchyChangeRequest::STATUS_APPROVED, // Already approved!
            'reviewed_by' => $this->owner->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'approve']
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_atomic_rollback_on_hierarchy_move_failure(): void
    {
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Atomic rollback test.',
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        $closuresBefore = UserClosure::all();
        $parentBefore = $this->childLla->parent_id;

        // Mock failure in moveAgent
        $mockService = $this->createMock(AgentHierarchyService::class);
        $mockService->method('moveAgent')
            ->willThrowException(new Exception('Simulated fatal closure table lock failure'));
        $this->app->instance(AgentHierarchyService::class, $mockService);

        try {
            $this->actingAs($this->owner)->post(
                route('admin.agents.hierarchy.review', $changeRequest->id),
                ['action' => 'approve']
            );
        } catch (Exception $e) {
            $this->assertEquals('Simulated fatal closure table lock failure', $e->getMessage());
        }

        // Entire transaction must rollback: request remains pending!
        $changeRequest->refresh();
        $this->assertEquals(HierarchyChangeRequest::STATUS_PENDING, $changeRequest->status);

        $this->childLla->refresh();
        $this->assertEquals($parentBefore, $this->childLla->parent_id);
        $this->assertEquals($closuresBefore->count(), UserClosure::count());
    }

    // =========================================================================
    // 4. QUEUE PAGE DATA PRIVACY & ACCESS CONTROL
    // =========================================================================

    public function test_hierarchy_requests_queue_excludes_sensitive_financial_and_kyc_data(): void
    {
        HierarchyChangeRequest::create([
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Privacy check request.',
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy-requests'));

        $response->assertStatus(200);

        // Raw response content verification: no NIC or bank details
        $content = $response->getContent();
        $this->assertStringNotContainsString('199844332211', $content); // childLla NIC
        $this->assertStringNotContainsString('4443332221', $content);   // childLla Bank Account
        $this->assertStringNotContainsString('wallet_balance', $content);
    }
}

