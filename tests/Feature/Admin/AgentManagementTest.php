<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\HierarchyChangeRequest;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $itManager;
    private User $marketingManager;
    private AgentHierarchyService $hierarchyService;

    protected function setUp(): void
    {
        parent::setUp();
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
    }

    public function test_management_can_view_agents_directory(): void
    {
        $response = $this->actingAs($this->marketingManager)->get(route('admin.agents.index'));
        $response->assertStatus(200);
    }

    public function test_admin_can_approve_pending_agent_and_attach_to_hierarchy(): void
    {
        $sponsor = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        $this->hierarchyService->attachAgent($sponsor, null);

        $applicant = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
            'parent_id' => $sponsor->id,
        ]);
        AgentProfile::create([
            'user_id' => $applicant->id,
            'nic' => '199612345678',
            'kyc_status' => 'pending',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $response = $this->actingAs($this->owner)->post(route('admin.agents.approve', $applicant->id));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $applicant->refresh();
        $this->assertEquals(UserStatus::ACTIVE, $applicant->status);

        // Verify hierarchy closure exists
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $sponsor->id,
            'descendant_id' => $applicant->id,
            'depth' => 1,
        ]);
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $applicant->id,
            'descendant_id' => $applicant->id,
            'depth' => 0,
        ]);
    }

    public function test_admin_can_reject_pending_agent_application(): void
    {
        $applicant = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
        ]);

        $response = $this->actingAs($this->owner)->post(route('admin.agents.reject', $applicant->id), [
            'reason' => 'Invalid identity documents provided.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $applicant->refresh();
        $this->assertEquals(UserStatus::SUSPENDED, $applicant->status);
    }

    public function test_admin_can_update_agent_kyc_verification_status(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        $profile = AgentProfile::create([
            'user_id' => $agent->id,
            'nic' => '199511112222',
            'kyc_status' => 'pending',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $response = $this->actingAs($this->itManager)->post(route('admin.agents.kyc.update', $agent->id), [
            'kyc_status' => 'verified',
            'kyc_notes' => 'NIC and bank passbook verified by compliance.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $profile->refresh();
        $this->assertEquals('verified', $profile->kyc_status);
        $this->assertEquals('NIC and bank passbook verified by compliance.', $profile->kyc_notes);
    }

    public function test_manager_can_request_hierarchy_move_and_owner_can_approve_it(): void
    {
        $rootA = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        $rootB = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        $childAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $rootA->id,
        ]);

        $this->hierarchyService->attachAgent($rootA, null);
        $this->hierarchyService->attachAgent($rootB, null);
        $this->hierarchyService->attachAgent($childAgent, $rootA);

        // Grant granular permission to the manager
        $permission = \App\Models\Permission::firstOrCreate(['name' => 'agents.hierarchy.request'], [
            'display_name' => 'Request Agent Hierarchy Move',
            'module' => 'agents',
            'guard_name' => 'web',
            'is_financial_restricted' => false,
        ]);
        $role = \App\Models\Role::firstOrCreate(['name' => 'it_manager'], [
            'display_name' => 'IT Manager',
            'description' => 'IT Manager',
            'is_system' => true,
            'is_protected' => false,
        ]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->itManager->update(['role_id' => $role->id]);

        // Submit move request
        $response = $this->actingAs($this->itManager)->post(route('admin.agents.hierarchy.request', $childAgent->id), [
            'requested_parent_id' => $rootB->id,
            'reason' => 'Relocated regional franchise territory to Root B.',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $changeRequest = HierarchyChangeRequest::where('agent_id', $childAgent->id)->first();
        $this->assertNotNull($changeRequest);
        $this->assertEquals(HierarchyChangeRequest::STATUS_PENDING, $changeRequest->status);

        // Non-owner cannot approve hierarchy move
        $forbiddenResponse = $this->actingAs($this->marketingManager)->post(route('admin.agents.hierarchy.review', $changeRequest->id), [
            'action' => 'approve',
            'review_notes' => 'Unauthorized approval attempt.',
        ]);
        $forbiddenResponse->assertStatus(403);

        // Owner approves hierarchy move
        $ownerResponse = $this->actingAs($this->owner)->post(route('admin.agents.hierarchy.review', $changeRequest->id), [
            'action' => 'approve',
            'review_notes' => 'Territory transfer approved by Owner.',
        ]);
        $ownerResponse->assertRedirect();
        $ownerResponse->assertSessionHas('success');

        $changeRequest->refresh();
        $this->assertEquals(HierarchyChangeRequest::STATUS_APPROVED, $changeRequest->status);

        // Verify hierarchy updated in database closures
        $childAgent->refresh();
        $this->assertEquals($rootB->id, $childAgent->parent_id);

        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $rootB->id,
            'descendant_id' => $childAgent->id,
            'depth' => 1,
        ]);
        $this->assertDatabaseMissing('user_closures', [
            'ancestor_id' => $rootA->id,
            'descendant_id' => $childAgent->id,
        ]);
    }

    public function test_sec_08_01_marketing_manager_receives_masked_nic_and_masked_bank_account(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $agent->id,
            'nic' => '199212345678',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo Fort',
            'bank_account_number' => '1002345678',
            'bank_account_name' => 'Test Agent',
            'kyc_status' => 'verified',
            'wallet_balance' => 10000.00,
        ]);

        $response = $this->actingAs($this->marketingManager)->get(route('admin.agents.show', $agent->id));
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Show')
            ->where('agent.agent_profile.nic', '********5678')
            ->where('agent.agent_profile.bank_account_number', '******5678')
            ->where('canViewFullFinancials', false)
            ->where('canViewFullKyc', false)
        );

        // Also test index directory masking
        $indexResponse = $this->actingAs($this->marketingManager)->get(route('admin.agents.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Index')
            ->where('agents.data.0.agent_profile.nic', '********5678')
            ->where('agents.data.0.agent_profile.bank_account_number', '******5678')
        );
    }

    public function test_sec_08_01_owner_and_accountant_retain_full_financial_and_kyc_access(): void
    {
        $accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);

        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $agent->id,
            'nic' => '199212345678',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo Fort',
            'bank_account_number' => '1002345678',
            'bank_account_name' => 'Test Agent',
            'kyc_status' => 'verified',
            'wallet_balance' => 10000.00,
        ]);

        // Owner access
        $ownerResponse = $this->actingAs($this->owner)->get(route('admin.agents.show', $agent->id));
        $ownerResponse->assertStatus(200);
        $ownerResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Show')
            ->where('agent.agent_profile.nic', '199212345678')
            ->where('agent.agent_profile.bank_account_number', '1002345678')
            ->where('canViewFullFinancials', true)
            ->where('canViewFullKyc', true)
        );

        // Accountant access
        $accountantResponse = $this->actingAs($accountant)->get(route('admin.agents.show', $agent->id));
        $accountantResponse->assertStatus(200);
        $accountantResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Show')
            ->where('agent.agent_profile.nic', '199212345678')
            ->where('agent.agent_profile.bank_account_number', '1002345678')
            ->where('canViewFullFinancials', true)
            ->where('canViewFullKyc', true)
        );
    }

    public function test_sec_08_01_it_manager_can_view_kyc_but_receives_masked_bank_account(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $agent->id,
            'nic' => '199212345678',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo Fort',
            'bank_account_number' => '1002345678',
            'bank_account_name' => 'Test Agent',
            'kyc_status' => 'verified',
            'wallet_balance' => 10000.00,
        ]);

        $response = $this->actingAs($this->itManager)->get(route('admin.agents.show', $agent->id));
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Show')
            ->where('agent.agent_profile.nic', '199212345678') // Full NIC retained for KYC verification
            ->where('agent.agent_profile.bank_account_number', '******5678') // Financial details masked
            ->where('canViewFullFinancials', false)
            ->where('canViewFullKyc', true)
        );
    }

    public function test_sec_08_01_unauthorized_direct_requests_rejected(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);

        // Customer cannot access agent management
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);
        $this->actingAs($customer)->get(route('admin.agents.show', $agent->id))->assertStatus(403);

        // Marketing manager cannot update KYC
        $this->actingAs($this->marketingManager)->post(route('admin.agents.kyc', $agent->id), [
            'kyc_status' => 'verified',
        ])->assertStatus(403);

        // Marketing manager cannot approve agents
        $this->actingAs($this->marketingManager)->post(route('admin.agents.approve', $agent->id))->assertStatus(403);

        // Marketing manager cannot update agent status
        $this->actingAs($this->marketingManager)->post(route('admin.agents.status', $agent->id), [
            'status' => 'suspended',
            'reason' => 'Unauthorized action',
        ])->assertStatus(403);
    }

    public function test_agent_manager_can_filter_directory_by_tier_role(): void
    {
        $agentManager = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $hla = User::factory()->create([
            'name' => 'Dilshan HLA',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);

        $lla = User::factory()->create([
            'name' => 'Saman LLA',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $hla->id,
        ]);

        // Filter by HLA
        $hlaResponse = $this->actingAs($agentManager)->get(route('admin.agents.index', ['role' => 'high_level_agent']));
        $hlaResponse->assertStatus(200);
        $hlaResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Index')
            ->where('agents.total', 1)
            ->where('agents.data.0.id', $hla->id)
            ->where('filters.role', 'high_level_agent')
        );

        // Filter by LLA
        $llaResponse = $this->actingAs($agentManager)->get(route('admin.agents.index', ['role' => 'low_level_agent']));
        $llaResponse->assertStatus(200);
        $llaResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Index')
            ->where('agents.total', 1)
            ->where('agents.data.0.id', $lla->id)
            ->where('filters.role', 'low_level_agent')
        );
    }

    public function test_agent_manager_can_filter_directory_by_status_and_kyc(): void
    {
        $agentManager = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $activeAgent = User::factory()->create([
            'name' => 'Active Agent',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $activeAgent->id,
            'kyc_status' => 'verified',
        ]);

        $pendingAgent = User::factory()->create([
            'name' => 'Pending Agent',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
        ]);
        AgentProfile::create([
            'user_id' => $pendingAgent->id,
            'kyc_status' => 'pending',
        ]);

        // Filter by Status: pending
        $statusResponse = $this->actingAs($agentManager)->get(route('admin.agents.index', ['status' => 'pending']));
        $statusResponse->assertStatus(200);
        $statusResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Index')
            ->where('agents.total', 1)
            ->where('agents.data.0.id', $pendingAgent->id)
        );

        // Filter by KYC Status: verified
        $kycResponse = $this->actingAs($agentManager)->get(route('admin.agents.index', ['kyc_status' => 'verified']));
        $kycResponse->assertStatus(200);
        $kycResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Index')
            ->where('agents.total', 1)
            ->where('agents.data.0.id', $activeAgent->id)
        );
    }

    public function test_agent_directory_includes_downline_counts_and_parent_info(): void
    {
        $agentManager = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $parent = User::factory()->create([
            'name' => 'Sponsor Parent',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'SPONSOR-99',
        ]);

        $child1 = User::factory()->create([
            'name' => 'Child 1',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $parent->id,
        ]);
        $child2 = User::factory()->create([
            'name' => 'Child 2',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $parent->id,
        ]);

        $response = $this->actingAs($agentManager)->get(route('admin.agents.index', ['search' => 'Sponsor Parent']));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Index')
            ->where('agents.data.0.id', $parent->id)
            ->where('agents.data.0.children_count', 2)
        );

        $childResponse = $this->actingAs($agentManager)->get(route('admin.agents.index', ['search' => 'Child 1']));
        $childResponse->assertStatus(200);
        $childResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Index')
            ->where('agents.data.0.id', $child1->id)
            ->where('agents.data.0.parent.name', 'Sponsor Parent')
            ->where('agents.data.0.parent.referral_code', 'SPONSOR-99')
            ->where('agents.data.0.children_count', 0)
        );
    }

    public function test_agent_manager_can_approve_and_reject_agent_applications(): void
    {
        $agentManager = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $applicant = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
        ]);
        AgentProfile::create([
            'user_id' => $applicant->id,
            'kyc_status' => 'pending',
        ]);

        // Agent Manager can approve
        $approveResponse = $this->actingAs($agentManager)->post(route('admin.agents.approve', $applicant->id));
        $approveResponse->assertRedirect();
        $applicant->refresh();
        $this->assertEquals(UserStatus::ACTIVE, $applicant->status);

        // Reset to pending for rejection test
        $applicant2 = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
        ]);
        AgentProfile::create([
            'user_id' => $applicant2->id,
            'kyc_status' => 'pending',
        ]);

        // Agent Manager can reject with reason
        $rejectResponse = $this->actingAs($agentManager)->post(route('admin.agents.reject', $applicant2->id), [
            'reason' => 'Invalid identity card documentation submitted.',
        ]);
        $rejectResponse->assertRedirect();
        $applicant2->refresh();
        $this->assertEquals(UserStatus::SUSPENDED, $applicant2->status);
    }
}
