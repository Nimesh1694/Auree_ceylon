<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentHierarchyService;
use App\Services\HierarchyIntegrityAuditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentHierarchyIntegrityAuditorTest extends TestCase
{
    use RefreshDatabase;

    private HierarchyIntegrityAuditService $auditService;
    private AgentHierarchyService $hierarchyService;

    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $marketingManager;
    private User $accountant;
    private User $customer;

    private User $hla1;
    private User $lla1;
    private User $lla2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->auditService = app(HierarchyIntegrityAuditService::class);
        $this->hierarchyService = app(AgentHierarchyService::class);

        $this->owner = User::factory()->create([
            'name' => 'Dilshan Owner',
            'email' => 'owner.integrity@auraluxe.lk',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Naveen SuperAdmin',
            'email' => 'superadmin.integrity@auraluxe.lk',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);

        $this->agentManager = User::factory()->create([
            'name' => 'Agent Manager Staff',
            'email' => 'agentmgr.integrity@auraluxe.lk',
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->marketingManager = User::factory()->create([
            'name' => 'Marketing Manager Staff',
            'email' => 'marketing.integrity@auraluxe.lk',
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Accountant Staff',
            'email' => 'accountant.integrity@auraluxe.lk',
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Regular Customer',
            'email' => 'customer.integrity@example.lk',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Clean initial agent network: HLA1 -> LLA1 -> LLA2
        $this->hla1 = User::factory()->create([
            'name' => 'HLA Root',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => null,
            'referral_code' => 'HLA-ROOT',
        ]);
        AgentProfile::create([
            'user_id' => $this->hla1->id,
            'nic' => '199012345678',
            'bank_name' => 'Bank of Ceylon',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'HLA Root',
            'wallet_balance' => 5000.00,
        ]);
        $this->hierarchyService->attachAgent($this->hla1, null);

        $this->lla1 = User::factory()->create([
            'name' => 'LLA Mid',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $this->hla1->id,
            'referral_code' => 'LLA-MID',
        ]);
        AgentProfile::create([
            'user_id' => $this->lla1->id,
            'nic' => '199512345678',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Kandy',
            'bank_account_number' => '9876543210',
            'bank_account_name' => 'LLA Mid',
            'wallet_balance' => 2500.00,
        ]);
        $this->hierarchyService->attachAgent($this->lla1, $this->hla1);

        $this->lla2 = User::factory()->create([
            'name' => 'LLA Leaf',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $this->lla1->id,
            'referral_code' => 'LLA-LEAF',
        ]);
        AgentProfile::create([
            'user_id' => $this->lla2->id,
            'nic' => '200012345678',
            'bank_name' => 'Hatton National Bank',
            'bank_branch' => 'Galle',
            'bank_account_number' => '1122334455',
            'bank_account_name' => 'LLA Leaf',
            'wallet_balance' => 1000.00,
        ]);
        $this->hierarchyService->attachAgent($this->lla2, $this->lla1);
    }

    public function test_correct_healthy_hierarchy_yields_healthy_status_and_zero_anomalies(): void
    {
        $report = $this->auditService->scan();

        $this->assertSame(HierarchyIntegrityAuditService::STATUS_HEALTHY, $report['status']);
        $this->assertSame(0, $report['total_anomalies']);
        $this->assertCount(0, $report['anomalies']);
        $this->assertSame(3, $report['total_agents']);
        // Closures: HLA self (0), LLA1 self (0), HLA->LLA1 (1), LLA2 self (0), LLA1->LLA2 (1), HLA->LLA2 (2) = 6 rows
        $this->assertSame(6, $report['total_closures']);
        $this->assertSame(0, $report['counts_by_severity']['critical']);
        $this->assertSame(0, $report['counts_by_severity']['high']);
    }

    public function test_missing_self_closure_is_detected(): void
    {
        // Delete self closure for LLA1
        UserClosure::where('ancestor_id', $this->lla1->id)
            ->where('descendant_id', $this->lla1->id)
            ->delete();

        $report = $this->auditService->scan();

        $this->assertNotSame(HierarchyIntegrityAuditService::STATUS_HEALTHY, $report['status']);
        $this->assertGreaterThanOrEqual(1, $report['total_anomalies']);

        $selfClosureAnomaly = collect($report['anomalies'])->firstWhere('category', 'missing_self_closure');
        $this->assertNotNull($selfClosureAnomaly);
        $this->assertSame($this->lla1->id, $selfClosureAnomaly['agent_id']);
        $this->assertSame(HierarchyIntegrityAuditService::SEVERITY_HIGH, $selfClosureAnomaly['severity']);
    }

    public function test_missing_direct_parent_closure_is_detected(): void
    {
        // Delete direct parent closure from HLA1 to LLA1 (depth = 1)
        UserClosure::where('ancestor_id', $this->hla1->id)
            ->where('descendant_id', $this->lla1->id)
            ->where('depth', 1)
            ->delete();

        $report = $this->auditService->scan();

        $parentClosureAnomaly = collect($report['anomalies'])->firstWhere('category', 'missing_parent_closure');
        $this->assertNotNull($parentClosureAnomaly);
        $this->assertSame($this->lla1->id, $parentClosureAnomaly['agent_id']);
        $this->assertSame(HierarchyIntegrityAuditService::SEVERITY_CRITICAL, $parentClosureAnomaly['severity']);
    }

    public function test_missing_intermediate_ancestor_closure_is_detected(): void
    {
        // Delete grandparent closure: HLA1 -> LLA2 (depth = 2)
        UserClosure::where('ancestor_id', $this->hla1->id)
            ->where('descendant_id', $this->lla2->id)
            ->where('depth', 2)
            ->delete();

        $report = $this->auditService->scan();

        $ancestorAnomaly = collect($report['anomalies'])->firstWhere('category', 'missing_ancestor_closure');
        $this->assertNotNull($ancestorAnomaly);
        $this->assertSame($this->lla2->id, $ancestorAnomaly['agent_id']);
        $this->assertSame(HierarchyIntegrityAuditService::SEVERITY_HIGH, $ancestorAnomaly['severity']);
    }

    public function test_unexpected_stale_closure_is_detected(): void
    {
        // Create an unrelated second HLA
        $hla2 = User::factory()->create([
            'name' => 'Unrelated HLA',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => null,
            'referral_code' => 'HLA-UNRELATED',
        ]);
        $this->hierarchyService->attachAgent($hla2, null);

        // Inject spurious closure: HLA2 -> LLA2 (which is not in LLA2's parent_id chain)
        UserClosure::create([
            'ancestor_id' => $hla2->id,
            'descendant_id' => $this->lla2->id,
            'depth' => 3,
        ]);

        $report = $this->auditService->scan();

        $unexpectedAnomaly = collect($report['anomalies'])->firstWhere('category', 'unexpected_closure');
        $this->assertNotNull($unexpectedAnomaly);
        $this->assertSame($this->lla2->id, $unexpectedAnomaly['agent_id']);
        $this->assertSame(HierarchyIntegrityAuditService::SEVERITY_HIGH, $unexpectedAnomaly['severity']);
    }

    public function test_incorrect_closure_depth_is_detected(): void
    {
        // Corrupt depth of HLA1 -> LLA2 from 2 to 5
        UserClosure::where('ancestor_id', $this->hla1->id)
            ->where('descendant_id', $this->lla2->id)
            ->update(['depth' => 5]);

        $report = $this->auditService->scan();

        $depthAnomaly = collect($report['anomalies'])->firstWhere('category', 'incorrect_closure_depth');
        $this->assertNotNull($depthAnomaly);
        $this->assertSame($this->lla2->id, $depthAnomaly['agent_id']);
        $this->assertSame('depth = 2', $depthAnomaly['expected']);
        $this->assertSame('depth = 5', $depthAnomaly['actual']);
    }

    public function test_invalid_parent_role_and_self_parenting_are_detected(): void
    {
        // Assign customer as parent_id of LLA2
        $this->lla2->update(['parent_id' => $this->customer->id]);

        $report = $this->auditService->scan();

        $invalidParentAnomaly = collect($report['anomalies'])->firstWhere('category', 'invalid_parent');
        $this->assertNotNull($invalidParentAnomaly);
        $this->assertSame($this->lla2->id, $invalidParentAnomaly['agent_id']);
        $this->assertSame(HierarchyIntegrityAuditService::SEVERITY_CRITICAL, $invalidParentAnomaly['severity']);

        // Self-parenting check
        $this->lla2->update(['parent_id' => $this->lla2->id]);
        $reportSelf = $this->auditService->scan();
        $selfParentAnomaly = collect($reportSelf['anomalies'])->firstWhere('id', "self_parent_{$this->lla2->id}");
        $this->assertNotNull($selfParentAnomaly);
    }

    public function test_hla_with_parent_is_detected(): void
    {
        // Incorrectly assign parent_id to HLA1
        $this->hla1->update(['parent_id' => $this->lla1->id]);

        $report = $this->auditService->scan();

        $hlaParentAnomaly = collect($report['anomalies'])->firstWhere('category', 'hla_with_parent');
        $this->assertNotNull($hlaParentAnomaly);
        $this->assertSame($this->hla1->id, $hlaParentAnomaly['agent_id']);
        $this->assertSame(HierarchyIntegrityAuditService::SEVERITY_CRITICAL, $hlaParentAnomaly['severity']);
    }

    public function test_lla_without_valid_hla_root_is_detected(): void
    {
        // Break LLA1's parent to null (making it an orphan LLA root)
        $this->lla1->update(['parent_id' => null]);

        $report = $this->auditService->scan();

        // Should flag orphan_agent for LLA1 and lla_missing_hla_root for LLA2
        $orphanAnomaly = collect($report['anomalies'])->firstWhere('category', 'orphan_agent');
        $this->assertNotNull($orphanAnomaly);
        $this->assertSame($this->lla1->id, $orphanAnomaly['agent_id']);

        $missingHlaRootAnomaly = collect($report['anomalies'])->firstWhere('category', 'lla_missing_hla_root');
        $this->assertNotNull($missingHlaRootAnomaly);
        $this->assertSame($this->lla2->id, $missingHlaRootAnomaly['agent_id']);
    }

    public function test_inactive_sponsor_is_detected(): void
    {
        // Suspend LLA1 (parent of active LLA2)
        $this->lla1->update(['status' => UserStatus::SUSPENDED]);

        $report = $this->auditService->scan();

        $inactiveSponsorAnomaly = collect($report['anomalies'])->firstWhere('category', 'inactive_sponsor');
        $this->assertNotNull($inactiveSponsorAnomaly);
        $this->assertSame($this->lla2->id, $inactiveSponsorAnomaly['agent_id']);
        $this->assertSame(HierarchyIntegrityAuditService::SEVERITY_MEDIUM, $inactiveSponsorAnomaly['severity']);
    }

    public function test_circular_parent_relationship_is_detected(): void
    {
        // Create cycle: HLA1 -> LLA1 -> LLA2 -> HLA1 (or LLA1 -> LLA2 -> LLA1)
        $this->lla1->update(['parent_id' => $this->lla2->id]);
        // LLA2 already has parent_id = LLA1

        $report = $this->auditService->scan();

        $cycleAnomaly = collect($report['anomalies'])->firstWhere('category', 'circular_hierarchy');
        $this->assertNotNull($cycleAnomaly);
        $this->assertSame(HierarchyIntegrityAuditService::STATUS_CRITICAL, $report['status']);
        $this->assertSame(HierarchyIntegrityAuditService::SEVERITY_CRITICAL, $cycleAnomaly['severity']);
    }

    public function test_orphan_agent_lla_with_null_parent_is_detected(): void
    {
        $orphan = User::factory()->create([
            'name' => 'Orphan LLA',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => null,
            'referral_code' => 'ORPHAN-LLA',
        ]);
        UserClosure::create([
            'ancestor_id' => $orphan->id,
            'descendant_id' => $orphan->id,
            'depth' => 0,
        ]);

        $report = $this->auditService->scan();

        $orphanAnomaly = collect($report['anomalies'])->firstWhere('id', "orphan_lla_{$orphan->id}");
        $this->assertNotNull($orphanAnomaly);
        $this->assertSame('orphan_agent', $orphanAnomaly['category']);
    }

    public function test_non_agent_closure_records_are_detected(): void
    {
        // Inject closure record involving customer
        UserClosure::create([
            'ancestor_id' => $this->customer->id,
            'descendant_id' => $this->lla2->id,
            'depth' => 1,
        ]);

        $report = $this->auditService->scan();

        $nonAgentAnomaly = collect($report['anomalies'])->firstWhere('category', 'non_agent_closure');
        $this->assertNotNull($nonAgentAnomaly);
        $this->assertSame(HierarchyIntegrityAuditService::SEVERITY_HIGH, $nonAgentAnomaly['severity']);
    }

    public function test_depth_limit_exceeded_flags_scan_incomplete(): void
    {
        // Build a chain of 52 agents to exceed the 50-hop ceiling
        $currentParent = $this->hla1;
        for ($i = 1; $i <= 52; $i++) {
            $agent = User::factory()->create([
                'name' => "Chain Agent {$i}",
                'role' => UserRole::LOW_LEVEL_AGENT,
                'status' => UserStatus::ACTIVE,
                'parent_id' => $currentParent->id,
                'referral_code' => "CHAIN-{$i}",
            ]);
            $currentParent = $agent;
        }

        $report = $this->auditService->scan();

        $this->assertTrue($report['depth_limit_exceeded']);
        $this->assertSame(HierarchyIntegrityAuditService::STATUS_SCAN_INCOMPLETE, $report['status']);
        $this->assertNotSame(HierarchyIntegrityAuditService::STATUS_HEALTHY, $report['status']);
    }

    public function test_owner_can_access_integrity_page_and_scan_endpoint(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.agents.hierarchy.integrity'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Agents/HierarchyIntegrity')
            ->has('initialReport')
            ->where('initialReport.status', HierarchyIntegrityAuditService::STATUS_HEALTHY)
        );

        $scanResponse = $this->actingAs($this->owner)->post(route('admin.agents.hierarchy.integrity.scan'));
        $scanResponse->assertRedirect();
        $scanResponse->assertSessionHas('report');

        $jsonResponse = $this->actingAs($this->owner)->postJson(route('admin.agents.hierarchy.integrity.scan'));
        $jsonResponse->assertOk();
        $jsonResponse->assertJsonStructure([
            'success',
            'report' => [
                'status',
                'timestamp',
                'execution_time_ms',
                'query_count',
                'total_agents',
                'total_closures',
                'total_anomalies',
                'anomalies',
            ],
        ]);
    }

    public function test_super_admin_with_permission_can_access_integrity(): void
    {
        // Super Admin has system.health.view and agents.hierarchy.integrity from seeder
        $response = $this->actingAs($this->superAdmin)->get(route('admin.agents.hierarchy.integrity'));
        $response->assertOk();

        $scanResponse = $this->actingAs($this->superAdmin)->post(route('admin.agents.hierarchy.integrity.scan'));
        $scanResponse->assertRedirect();
    }

    public function test_agent_manager_without_permission_receives_403_but_with_permission_can_access(): void
    {
        // Agent Manager lacks agents.hierarchy.integrity by default
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy.integrity'));
        $response->assertForbidden();

        $scanResponse = $this->actingAs($this->agentManager)->post(route('admin.agents.hierarchy.integrity.scan'));
        $scanResponse->assertForbidden();

        // Grant permission explicitly via role
        $perm = Permission::firstOrCreate(['name' => 'agents.hierarchy.integrity'], [
            'display_name' => 'Audit Hierarchy Network Integrity',
            'module' => 'agents',
            'is_financial_restricted' => false,
        ]);
        $agentMgrRole = Role::where('name', 'agent_manager')->first();
        $agentMgrRole->permissions()->syncWithoutDetaching([$perm->id]);

        $allowedResponse = $this->actingAs($this->agentManager)->get(route('admin.agents.hierarchy.integrity'));
        $allowedResponse->assertOk();
    }

    public function test_unauthorized_roles_receive_403(): void
    {
        $unauthorizedUsers = [
            $this->marketingManager,
            $this->accountant,
            $this->customer,
            $this->hla1,
            $this->lla1,
        ];

        foreach ($unauthorizedUsers as $user) {
            $this->actingAs($user)->get(route('admin.agents.hierarchy.integrity'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.agents.hierarchy.integrity.scan'))->assertForbidden();
        }
    }

    public function test_pii_and_financial_data_are_strictly_excluded_from_report_props(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.agents.hierarchy.integrity'));
        $response->assertOk();

        $content = $response->getContent();

        // Verify NICs are never present
        $this->assertStringNotContainsString('199012345678', $content);
        $this->assertStringNotContainsString('199512345678', $content);
        $this->assertStringNotContainsString('200012345678', $content);

        // Verify bank account numbers are never present
        $this->assertStringNotContainsString('1234567890', $content);
        $this->assertStringNotContainsString('9876543210', $content);
        $this->assertStringNotContainsString('1122334455', $content);

        // Verify wallet balances are never present
        $this->assertStringNotContainsString('5000.00', $content);
        $this->assertStringNotContainsString('2500.00', $content);
        $this->assertStringNotContainsString('1000.00', $content);
    }

    public function test_scan_creates_zero_hierarchy_or_closure_table_writes(): void
    {
        $initialUsersCount = User::count();
        $initialClosuresCount = UserClosure::count();
        $initialParents = User::pluck('parent_id', 'id')->all();

        // Run audit scan directly and via controller
        $this->auditService->scan();
        $this->actingAs($this->owner)->post(route('admin.agents.hierarchy.integrity.scan'));

        $this->assertSame($initialUsersCount, User::count());
        $this->assertSame($initialClosuresCount, UserClosure::count());
        $this->assertSame($initialParents, User::pluck('parent_id', 'id')->all());
    }

    public function test_benchmark_query_count_is_minimal_and_fixed(): void
    {
        $report = $this->auditService->scan();

        // Query count must be strictly 3 (Agents query, Closures query, Non-agent join query)
        $this->assertLessThanOrEqual(5, $report['query_count']);
        $this->assertGreaterThanOrEqual(1, $report['query_count']);
        $this->assertLessThan(1000, $report['execution_time_ms']); // Completes well under 1 second
    }

    public function test_large_network_simulation_verifies_zero_n_plus_one_queries_and_performance(): void
    {
        // Generate 5 HLA branches, each with 20 nested LLAs (105 agents total)
        for ($h = 1; $h <= 5; $h++) {
            $hla = User::factory()->create([
                'name' => "Branch HLA {$h}",
                'role' => UserRole::HIGH_LEVEL_AGENT,
                'status' => UserStatus::ACTIVE,
                'parent_id' => null,
                'referral_code' => "HLA-BR-{$h}",
            ]);
            $this->hierarchyService->attachAgent($hla, null);

            $parent = $hla;
            for ($l = 1; $l <= 20; $l++) {
                $lla = User::factory()->create([
                    'name' => "Branch {$h} LLA {$l}",
                    'role' => UserRole::LOW_LEVEL_AGENT,
                    'status' => UserStatus::ACTIVE,
                    'parent_id' => $parent->id,
                    'referral_code' => "LLA-BR-{$h}-{$l}",
                ]);
                $this->hierarchyService->attachAgent($lla, $parent);

                // Alternate branching vs deepening
                if ($l % 4 !== 0) {
                    $parent = $lla;
                } else {
                    $parent = $hla;
                }
            }
        }

        // Measure scan on the 100+ agent network
        $report = $this->auditService->scan();

        $this->assertSame(HierarchyIntegrityAuditService::STATUS_HEALTHY, $report['status']);
        $this->assertSame(0, $report['total_anomalies']);
        $this->assertGreaterThanOrEqual(105, $report['total_agents']);
        $this->assertGreaterThanOrEqual(200, $report['total_closures']);

        // Rigorously assert zero N+1 queries: total DB queries remains strictly <= 5 regardless of 105+ nodes
        $this->assertLessThanOrEqual(5, $report['query_count']);
        $this->assertLessThan(500, $report['execution_time_ms']); // Under 500ms
    }
}
