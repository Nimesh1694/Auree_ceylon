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
use App\Services\HierarchyIntegrityAuditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase4FinalIntegrationAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;
    private AgentInvitationService $invitationService;
    private CommissionService $commissionService;
    private HierarchyIntegrityAuditService $auditService;

    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $itManager;
    private User $accountant;
    private User $marketingManager;
    private User $operationsManager;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Setting::set('direct_agent_commission_percent', 25.0, 'commission', 'float');
        Setting::set('upline_agent_commission_percent', 10.0, 'commission', 'float');

        $this->hierarchyService = app(AgentHierarchyService::class);
        $this->invitationService = app(AgentInvitationService::class);
        $this->commissionService = app(CommissionService::class);
        $this->auditService = app(HierarchyIntegrityAuditService::class);

        // System users representing the 10 system roles
        $this->owner = User::factory()->create([
            'name' => 'Owner Dilshan',
            'email' => 'owner.final@auraluxe.lk',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Naveen',
            'email' => 'superadmin.final@auraluxe.lk',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);

        $this->agentManager = User::factory()->create([
            'name' => 'Agent Manager User',
            'email' => 'agentmgr.final@auraluxe.lk',
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->itManager = User::factory()->create([
            'name' => 'IT Manager User',
            'email' => 'itops.final@auraluxe.lk',
            'role' => UserRole::IT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Accountant User',
            'email' => 'accountant.final@auraluxe.lk',
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->marketingManager = User::factory()->create([
            'name' => 'Marketing Manager User',
            'email' => 'marketing.final@auraluxe.lk',
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->operationsManager = User::factory()->create([
            'name' => 'Operations Manager User',
            'email' => 'operations.final@auraluxe.lk',
            'role' => UserRole::OPERATIONS_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Customer User',
            'email' => 'customer.final@example.lk',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    private function createOrderForAgent(User $agent, string $subtotal = '10000.00'): Order
    {
        return Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'customer_id' => $this->customer->id,
            'customer_name' => 'Customer User',
            'customer_email' => 'customer.final@example.lk',
            'customer_phone' => '0771234567',
            'shipping_address' => '456 Test Street, Colombo',
            'agent_id' => $agent->id,
            'referral_code' => $agent->referral_code,
            'subtotal' => $subtotal,
            'delivery_charge' => '350.00',
            'total_amount' => bcadd($subtotal, '350.00', 2),
            'currency' => 'LKR',
            'payment_method' => 'cod',
            'status' => 'confirmed',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'tracking_token' => 'TRK-' . uniqid(),
        ]);
    }

    /**
     * 1. End-to-end Registration -> Closures -> Tree Explorer -> Commission -> Move -> Integrity.
     */
    public function test_complete_end_to_end_hierarchy_lifecycle_and_commission_integrity(): void
    {
        // -------------------------------------------------------------
        // Step A: Candidate registers via HLA invitation
        // -------------------------------------------------------------
        $hlaInvResult = $this->invitationService->createInvitation(
            $this->agentManager,
            AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            [
                'name' => 'Candidate HLA',
                'email' => 'candidate.hla@auraluxe.lk',
                'phone' => '0771000001',
            ]
        );
        $hlaPlainToken = $hlaInvResult['token'];

        $hlaRegResponse = $this->post(route('agent.register.store'), [
            'token' => $hlaPlainToken,
            'name' => 'Candidate HLA',
            'email' => 'candidate.hla@auraluxe.lk',
            'phone' => '0771000001',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199011112222',
            'address' => '100 Primary Lane, Colombo',
            'bank_name' => 'Bank of Ceylon',
            'bank_branch' => 'City',
            'bank_account_number' => '111122223333',
            'bank_account_name' => 'Candidate HLA',
        ]);
        $hlaRegResponse->assertRedirect(route('login'));

        $hla = User::where('email', 'candidate.hla@auraluxe.lk')->firstOrFail();
        $this->assertNull($hla->parent_id);
        $this->assertSame(UserRole::HIGH_LEVEL_AGENT, $hla->role);
        // Verify self-closure created at registration
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $hla->id,
            'descendant_id' => $hla->id,
            'depth' => 0,
        ]);

        // Activate HLA
        $this->actingAs($this->agentManager)->post(route('admin.agents.approve', $hla->id));
        $hla->refresh();
        $this->assertSame(UserStatus::ACTIVE, $hla->status);

        // -------------------------------------------------------------
        // Step B: LLA registers under HLA via LLA invitation
        // -------------------------------------------------------------
        $llaInvResult = $this->invitationService->createInvitation(
            $hla,
            AgentInvitation::TYPE_LOW_LEVEL_AGENT,
            [
                'name' => 'Candidate LLA1',
                'email' => 'candidate.lla1@auraluxe.lk',
                'phone' => '0772000002',
            ]
        );
        $llaPlainToken = $llaInvResult['token'];

        $llaRegResponse = $this->post(route('agent.register.store'), [
            'token' => $llaPlainToken,
            'name' => 'Candidate LLA1',
            'email' => 'candidate.lla1@auraluxe.lk',
            'phone' => '0772000002',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199511112222',
            'address' => '200 Secondary Rd, Kandy',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Kandy',
            'bank_account_number' => '222233334444',
            'bank_account_name' => 'Candidate LLA1',
        ]);
        $llaRegResponse->assertRedirect(route('login'));

        $lla1 = User::where('email', 'candidate.lla1@auraluxe.lk')->firstOrFail();
        $this->assertSame($hla->id, $lla1->parent_id);
        $this->assertSame(UserRole::LOW_LEVEL_AGENT, $lla1->role);

        // Activate LLA1
        $this->actingAs($this->agentManager)->post(route('admin.agents.approve', $lla1->id));
        $lla1->refresh();

        // -------------------------------------------------------------
        // Step C: Nested LLA2 registers using LLA1's referral code
        // -------------------------------------------------------------
        $lla2RegResponse = $this->post(route('agent.register.store'), [
            'referral_code' => $lla1->referral_code,
            'name' => 'Candidate LLA2',
            'email' => 'candidate.lla2@auraluxe.lk',
            'phone' => '0773000003',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '200011112222',
            'address' => '300 Tertiary Rd, Galle',
            'bank_name' => 'Hatton National Bank',
            'bank_branch' => 'Galle',
            'bank_account_number' => '333344445555',
            'bank_account_name' => 'Candidate LLA2',
        ]);
        $lla2RegResponse->assertRedirect(route('login'));

        $lla2 = User::where('email', 'candidate.lla2@auraluxe.lk')->firstOrFail();
        $this->assertSame($lla1->id, $lla2->parent_id);
        $this->actingAs($this->agentManager)->post(route('admin.agents.approve', $lla2->id));
        $lla2->refresh();

        // -------------------------------------------------------------
        // Step D: Verify Closure Table Completeness
        // -------------------------------------------------------------
        // Closures for LLA2:
        // LLA2 self (0), LLA1 parent (1), HLA grandparent (2)
        $this->assertDatabaseHas('user_closures', ['ancestor_id' => $lla2->id, 'descendant_id' => $lla2->id, 'depth' => 0]);
        $this->assertDatabaseHas('user_closures', ['ancestor_id' => $lla1->id, 'descendant_id' => $lla2->id, 'depth' => 1]);
        $this->assertDatabaseHas('user_closures', ['ancestor_id' => $hla->id, 'descendant_id' => $lla2->id, 'depth' => 2]);

        // -------------------------------------------------------------
        // Step E: Commission Engine Verification (Sale 1)
        // -------------------------------------------------------------
        $order1 = $this->createOrderForAgent($lla2, '10000.00');
        $entries1 = $this->commissionService->calculateAndRecordPendingCommissions($order1);

        $this->assertCount(2, $entries1);
        $direct1 = $entries1->firstWhere('commission_type', CommissionLedgerEntry::TYPE_DIRECT_LOW_LEVEL);
        $override1 = $entries1->firstWhere('commission_type', CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE);

        $this->assertSame($lla2->id, $direct1->agent_id);
        $this->assertSame('2500.00', $direct1->calculated_amount);
        $this->assertEquals(25.0, (float) $direct1->rate_percentage);

        // Immediate root HLA gets the 10% override (Intermediate LLA1 does NOT receive override)
        $this->assertSame($hla->id, $override1->agent_id);
        $this->assertSame('1000.00', $override1->calculated_amount);
        $this->assertEquals(10.0, (float) $override1->rate_percentage);

        $initialOverrideLedgerId = $override1->id;

        // -------------------------------------------------------------
        // Step F: Hierarchy Move Request & Owner Approval
        // -------------------------------------------------------------
        // Create second HLA branch (HLA-B)
        $hlaB = User::factory()->create([
            'name' => 'HLA Branch B',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => null,
            'referral_code' => 'HLA-BRANCH-B',
        ]);
        $this->hierarchyService->attachAgent($hlaB, null);

        // Agent Manager submits move request to relocate LLA1 and entire subtree under HLA-B
        $submitResponse = $this->actingAs($this->agentManager)->post(route('admin.agents.hierarchy-requests.store'), [
            'agent_id' => $lla1->id,
            'requested_parent_id' => $hlaB->id,
            'reason' => 'Strategic regional reassignment of Kandy branch',
        ]);
        $submitResponse->assertRedirect();

        $changeRequest = HierarchyChangeRequest::where('agent_id', $lla1->id)->where('status', 'pending')->firstOrFail();
        $this->assertSame($hla->id, $changeRequest->current_parent_id);
        $this->assertSame($hlaB->id, $changeRequest->requested_parent_id);

        // Super Admin cannot approve -> 403
        $this->actingAs($this->superAdmin)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'approve']
        )->assertForbidden();

        // Agent Manager cannot approve -> 403
        $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'approve']
        )->assertForbidden();

        // Owner approves request -> 302
        $approveResponse = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'approve', 'decision_notes' => 'Executive realignment approved']
        );
        $approveResponse->assertRedirect();
        $approveResponse->assertSessionHas('success');

        $changeRequest->refresh();
        $this->assertSame('approved', $changeRequest->status);

        // Verify parent updated
        $lla1->refresh();
        $this->assertSame($hlaB->id, $lla1->parent_id);

        // Verify closures re-indexed:
        // LLA1 is now under HLA-B
        $this->assertDatabaseHas('user_closures', ['ancestor_id' => $hlaB->id, 'descendant_id' => $lla1->id, 'depth' => 1]);
        $this->assertDatabaseMissing('user_closures', ['ancestor_id' => $hla->id, 'descendant_id' => $lla1->id]);

        // LLA2 (child of LLA1) is also under HLA-B now (depth 2)
        $this->assertDatabaseHas('user_closures', ['ancestor_id' => $hlaB->id, 'descendant_id' => $lla2->id, 'depth' => 2]);
        $this->assertDatabaseMissing('user_closures', ['ancestor_id' => $hla->id, 'descendant_id' => $lla2->id]);

        // -------------------------------------------------------------
        // Step G: Commission Regression & Historical Snapshot Immutability
        // -------------------------------------------------------------
        // Prior commission ledger record MUST remain 100% identical!
        $priorLedgerRecord = CommissionLedgerEntry::find($initialOverrideLedgerId);
        $this->assertSame($hla->id, $priorLedgerRecord->agent_id, 'Historical commission must NEVER change agent recipient');
        $this->assertSame('1000.00', $priorLedgerRecord->calculated_amount);

        // New order placed by LLA2 after relocation:
        $order2 = $this->createOrderForAgent($lla2, '20000.00');
        $entries2 = $this->commissionService->calculateAndRecordPendingCommissions($order2);

        $direct2 = $entries2->firstWhere('commission_type', CommissionLedgerEntry::TYPE_DIRECT_LOW_LEVEL);
        $override2 = $entries2->firstWhere('commission_type', CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE);

        $this->assertSame($lla2->id, $direct2->agent_id);
        $this->assertSame('5000.00', $direct2->calculated_amount); // 25% of 20000

        // Future override now goes to NEW HLA-B root!
        $this->assertSame($hlaB->id, $override2->agent_id);
        $this->assertSame('2000.00', $override2->calculated_amount); // 10% of 20000

        // -------------------------------------------------------------
        // Step H: Integrity Auditor Scan on Final Network
        // -------------------------------------------------------------
        $auditReport = $this->auditService->scan();
        $this->assertSame(HierarchyIntegrityAuditService::STATUS_HEALTHY, $auditReport['status']);
        $this->assertSame(0, $auditReport['total_anomalies']);
        $this->assertCount(0, $auditReport['anomalies']);
    }

    /**
     * 2. Strict Role Security Matrix Verification for all Phase 4 endpoints.
     */
    public function test_strict_role_and_permission_matrix_across_all_endpoints(): void
    {
        $hla = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => null,
            'referral_code' => 'HLA-SEC',
        ]);
        $this->hierarchyService->attachAgent($hla, null);

        $lla = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $hla->id,
            'referral_code' => 'LLA-SEC',
        ]);
        $this->hierarchyService->attachAgent($lla, $hla);

        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $lla->id,
            'current_parent_id' => $hla->id,
            'requested_parent_id' => null,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Security matrix test',
            'status' => 'pending',
        ]);

        // Endpoints to audit:
        // 1. Tree Page: GET /admin/agents/hierarchy
        // 2. Nodes API: GET /admin/agents/hierarchy/nodes
        // 3. Requests Page: GET /admin/agents/hierarchy-requests
        // 4. Request Review: POST /admin/agents/hierarchy-requests/{id}/review
        // 5. Integrity Page: GET /admin/agents/hierarchy/integrity
        // 6. Integrity Scan: POST /admin/agents/hierarchy/integrity/scan

        // --- OWNER (Unrestricted full access across everything) ---
        $this->actingAs($this->owner)->get(route('admin.agents.hierarchy'))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.agents.hierarchy.nodes', ['parent_id' => $hla->id]))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.agents.hierarchy-requests'))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.agents.hierarchy.integrity'))->assertOk();
        $this->actingAs($this->owner)->post(route('admin.agents.hierarchy.integrity.scan'))->assertRedirect();

        // --- SUPER ADMIN (Technical authority: Can view tree, requests, integrity; CANNOT review move requests) ---
        $this->actingAs($this->superAdmin)->get(route('admin.agents.hierarchy'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('admin.agents.hierarchy.nodes', ['parent_id' => $hla->id]))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('admin.agents.hierarchy-requests'))->assertOk();
        $this->actingAs($this->superAdmin)->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('admin.agents.hierarchy.integrity'))->assertOk();

        // --- AGENT MANAGER (Operational authority: Can view tree, submit requests; CANNOT review requests, CANNOT access integrity by default) ---
        $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy'))->assertOk();
        $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy.nodes', ['parent_id' => $hla->id]))->assertOk();
        $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy-requests'))->assertOk();
        $this->actingAs($this->agentManager)->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])->assertForbidden();
        $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy.integrity'))->assertForbidden();
        $this->actingAs($this->agentManager)->post(route('admin.agents.hierarchy.integrity.scan'))->assertForbidden();

        // --- MARKETING MANAGER (Blocked across all hierarchy administration) ---
        $this->actingAs($this->marketingManager)->get(route('admin.agents.hierarchy'))->assertForbidden();
        $this->actingAs($this->marketingManager)->get(route('admin.agents.hierarchy.nodes'))->assertForbidden();
        $this->actingAs($this->marketingManager)->get(route('admin.agents.hierarchy-requests'))->assertForbidden();
        $this->actingAs($this->marketingManager)->post(route('admin.agents.hierarchy.review', $changeRequest->id))->assertForbidden();
        $this->actingAs($this->marketingManager)->get(route('admin.agents.hierarchy.integrity'))->assertForbidden();

        // --- ACCOUNTANT (Blocked across all hierarchy administration) ---
        $this->actingAs($this->accountant)->get(route('admin.agents.hierarchy'))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('admin.agents.hierarchy.nodes'))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('admin.agents.hierarchy-requests'))->assertForbidden();
        $this->actingAs($this->accountant)->post(route('admin.agents.hierarchy.review', $changeRequest->id))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('admin.agents.hierarchy.integrity'))->assertForbidden();

        // --- OPERATIONS MANAGER (Blocked across all hierarchy administration) ---
        $this->actingAs($this->operationsManager)->get(route('admin.agents.hierarchy'))->assertForbidden();
        $this->actingAs($this->operationsManager)->get(route('admin.agents.hierarchy.nodes'))->assertForbidden();
        $this->actingAs($this->operationsManager)->get(route('admin.agents.hierarchy-requests'))->assertForbidden();
        $this->actingAs($this->operationsManager)->post(route('admin.agents.hierarchy.review', $changeRequest->id))->assertForbidden();
        $this->actingAs($this->operationsManager)->get(route('admin.agents.hierarchy.integrity'))->assertForbidden();

        // --- CUSTOMER & REGULAR AGENTS (Blocked across all admin hierarchy routes) ---
        $this->actingAs($this->customer)->get(route('admin.agents.hierarchy'))->assertForbidden();
        $this->actingAs($this->customer)->get(route('admin.agents.hierarchy.nodes'))->assertForbidden();
        $this->actingAs($this->customer)->get(route('admin.agents.hierarchy-requests'))->assertForbidden();
        $this->actingAs($this->customer)->post(route('admin.agents.hierarchy.review', $changeRequest->id))->assertForbidden();
        $this->actingAs($this->customer)->get(route('admin.agents.hierarchy.integrity'))->assertForbidden();

        $this->actingAs($hla)->get(route('admin.agents.hierarchy'))->assertForbidden();
        $this->actingAs($lla)->get(route('admin.agents.hierarchy'))->assertForbidden();
    }

    /**
     * 3. Stale Request Rejection and Concurrency Safeguards.
     */
    public function test_stale_request_auto_rejection_and_replay_protection(): void
    {
        $hlaA = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE, 'parent_id' => null]);
        $hlaB = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE, 'parent_id' => null]);
        $hlaC = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE, 'parent_id' => null]);
        $this->hierarchyService->attachAgent($hlaA, null);
        $this->hierarchyService->attachAgent($hlaB, null);
        $this->hierarchyService->attachAgent($hlaC, null);

        $lla = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE, 'parent_id' => $hlaA->id]);
        $this->hierarchyService->attachAgent($lla, $hlaA);

        // Move request created when parent was HLA-A to move to HLA-B
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $lla->id,
            'current_parent_id' => $hlaA->id,
            'requested_parent_id' => $hlaB->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Stale test',
            'status' => 'pending',
        ]);

        // Out of band, LLA's parent changes to HLA-C before Owner reviews the request
        $lla->update(['parent_id' => $hlaC->id]);

        // Owner attempts to approve -> auto-rejected due to stale state!
        $response = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'approve']
        );
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $changeRequest->refresh();
        $this->assertSame('rejected', $changeRequest->status);
        $this->assertStringContainsString('stale', strtolower($changeRequest->review_notes));

        // Replay attempt on already-rejected request -> error redirect
        $replayResponse = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'approve']
        );
        $replayResponse->assertRedirect();
        $replayResponse->assertSessionHas('error');
    }
}
