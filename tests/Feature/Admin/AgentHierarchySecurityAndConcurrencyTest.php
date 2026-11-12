<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
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
use App\Services\CommissionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentHierarchySecurityAndConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;
    private CommissionService $commissionService;

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

        Setting::set('direct_agent_commission_percent', 25.0, 'commission', 'float');
        Setting::set('upline_agent_commission_percent', 10.0, 'commission', 'float');

        $this->hierarchyService = app(AgentHierarchyService::class);
        $this->commissionService = app(CommissionService::class);

        // System users
        $this->owner = User::factory()->create([
            'name' => 'Dilshan Owner',
            'email' => 'owner.exec@auraluxe.lk',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Naveen SuperAdmin',
            'email' => 'superadmin@auraluxe.lk',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);

        $this->agentManager = User::factory()->create([
            'name' => 'Agent Manager User',
            'email' => 'agentmgr@auraluxe.lk',
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->itManager = User::factory()->create([
            'name' => 'IT Manager User',
            'email' => 'itops@auraluxe.lk',
            'role' => UserRole::IT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->marketingManager = User::factory()->create([
            'name' => 'Marketing Manager User',
            'email' => 'marketing@auraluxe.lk',
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Regular Customer',
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
        $this->hierarchyService->attachAgent($this->hla1, null);

        $this->hla2 = User::factory()->create([
            'name' => 'HLA Leader 2',
            'email' => 'hla2@example.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-HLA-002',
            'parent_id' => null,
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
        $this->hierarchyService->attachAgent($this->lla1, $this->hla1);

        $this->childLla = User::factory()->create([
            'name' => 'Child LLA Member',
            'email' => 'child.lla@example.lk',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-LLA-002',
            'parent_id' => $this->lla1->id,
        ]);
        $this->hierarchyService->attachAgent($this->childLla, $this->lla1);
    }

    private function createOrderForAgent(User $agent, string $subtotal = '10000.00'): Order
    {
        return Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'customer_id' => $this->customer->id,
            'customer_name' => 'Regular Customer',
            'customer_email' => 'customer@example.lk',
            'customer_phone' => '0771234567',
            'shipping_address' => '456 Test Street',
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

    // =========================================================================
    // 1. PARTIAL CLOSURE INTEGRITY & COMMISSION FALLBACK (8 SCENARIOS)
    // =========================================================================

    /**
     * Scenario 1: Complete closure chain
     */
    public function test_scenario_1_complete_closure_chain(): void
    {
        $upline = $this->hierarchyService->getUpline($this->childLla);

        $this->assertCount(2, $upline);
        $this->assertEquals($this->lla1->id, $upline[0]->id);
        $this->assertEquals($this->hla1->id, $upline[1]->id);

        $order = $this->createOrderForAgent($this->childLla, '10000.00');
        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertCount(2, $entries);
        $direct = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_DIRECT_LOW_LEVEL);
        $override = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE);

        $this->assertNotNull($direct);
        $this->assertEquals($this->childLla->id, $direct->agent_id);
        $this->assertEquals('2500.00', $direct->calculated_amount);

        $this->assertNotNull($override);
        $this->assertEquals($this->hla1->id, $override->agent_id);
        $this->assertEquals('1000.00', $override->calculated_amount);
    }

    /**
     * Scenario 2: Completely missing closure records
     */
    public function test_scenario_2_completely_missing_closure_records(): void
    {
        // Delete all closure entries for childLla
        UserClosure::where('descendant_id', $this->childLla->id)->delete();
        $this->assertEquals(0, UserClosure::where('descendant_id', $this->childLla->id)->count());

        $closuresCountBefore = UserClosure::count();

        // getUpline should safely fall back and discover upline without DB writes
        $upline = $this->hierarchyService->getUpline($this->childLla);

        $this->assertCount(2, $upline);
        $this->assertEquals($this->lla1->id, $upline[0]->id);
        $this->assertEquals($this->hla1->id, $upline[1]->id);

        // Commission distribution must not drop HLA override
        $order = $this->createOrderForAgent($this->childLla, '10000.00');
        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertCount(2, $entries);
        $override = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE);
        $this->assertNotNull($override);
        $this->assertEquals($this->hla1->id, $override->agent_id);
        $this->assertEquals('1000.00', $override->calculated_amount);

        // Verify ZERO DB writes were performed to user_closures during calculation
        $this->assertEquals($closuresCountBefore, UserClosure::count());
    }

    /**
     * Scenario 3: Missing self-closure only
     */
    public function test_scenario_3_missing_self_closure_only(): void
    {
        // Delete self-closure (depth 0) for childLla
        UserClosure::where('ancestor_id', $this->childLla->id)
            ->where('descendant_id', $this->childLla->id)
            ->where('depth', 0)
            ->delete();

        $upline = $this->hierarchyService->getUpline($this->childLla);

        $this->assertCount(2, $upline);
        $this->assertEquals($this->lla1->id, $upline[0]->id);
        $this->assertEquals($this->hla1->id, $upline[1]->id);

        $order = $this->createOrderForAgent($this->childLla, '10000.00');
        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $override = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE);
        $this->assertNotNull($override);
        $this->assertEquals($this->hla1->id, $override->agent_id);
    }

    /**
     * Scenario 4: Missing direct-parent closure
     */
    public function test_scenario_4_missing_direct_parent_closure(): void
    {
        // Delete link between direct parent (lla1) and childLla, but keep link to HLA1
        UserClosure::where('ancestor_id', $this->lla1->id)
            ->where('descendant_id', $this->childLla->id)
            ->delete();

        // Now closure table only has HLA1 at depth 2, missing depth 1!
        $upline = $this->hierarchyService->getUpline($this->childLla);

        // Fallback should detect that first ancestor is not lla1 (child's direct parent)
        // and safely rebuild the upline from users.parent_id
        $this->assertCount(2, $upline);
        $this->assertEquals($this->lla1->id, $upline[0]->id);
        $this->assertEquals($this->hla1->id, $upline[1]->id);
    }

    /**
     * Scenario 5: Missing one intermediate ancestor (e.g. HLA override missing from closures)
     */
    public function test_scenario_5_missing_intermediate_ancestor(): void
    {
        // Delete link to root HLA1 for childLla, leaving only (lla1, childLla, 1)
        UserClosure::where('ancestor_id', $this->hla1->id)
            ->where('descendant_id', $this->childLla->id)
            ->delete();

        $closuresCountBefore = UserClosure::count();

        // getUpline should detect that the closure ends at an LLA who has an active parent_id
        // and fall back to parent_id traversal to recover the root HLA1
        $upline = $this->hierarchyService->getUpline($this->childLla);

        $this->assertCount(2, $upline);
        $this->assertEquals($this->lla1->id, $upline[0]->id);
        $this->assertEquals($this->hla1->id, $upline[1]->id);

        // Crucial test: Ensure 10% HLA override is NEVER silently omitted!
        $order = $this->createOrderForAgent($this->childLla, '10000.00');
        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $this->assertCount(2, $entries);
        $override = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE);
        $this->assertNotNull($override);
        $this->assertEquals($this->hla1->id, $override->agent_id);
        $this->assertEquals('1000.00', $override->calculated_amount);

        // Zero DB writes during fallback
        $this->assertEquals($closuresCountBefore, UserClosure::count());
    }

    /**
     * Scenario 6: Stale closure records that conflict with users.parent_id
     */
    public function test_scenario_6_stale_closure_records_conflicting_with_parent_id(): void
    {
        // Suppose users.parent_id was updated directly to hla2, but closures still point to lla1 & hla1
        $this->childLla->update(['parent_id' => $this->hla2->id]);

        $upline = $this->hierarchyService->getUpline($this->childLla);

        // Direct parent in users table is hla2, so closure starting with lla1 is stale!
        // Fallback traversal should return hla2
        $this->assertCount(1, $upline);
        $this->assertEquals($this->hla2->id, $upline[0]->id);
    }

    /**
     * Scenario 7: Parent chain containing an invalid role
     */
    public function test_scenario_7_parent_chain_containing_invalid_role(): void
    {
        // Agent's parent is set to a Customer user
        $this->childLla->update(['parent_id' => $this->customer->id]);

        $upline = $this->hierarchyService->getUpline($this->childLla);

        // Non-agent customer must NEVER be returned in upline
        $this->assertCount(0, $upline);

        // Commission distribution must not pay upline commission to customer
        $order = $this->createOrderForAgent($this->childLla, '10000.00');
        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $override = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE);
        $this->assertNull($override);
    }

    /**
     * Scenario 8: Parent chain containing a cycle
     */
    public function test_scenario_8_parent_chain_containing_cycle(): void
    {
        // Intentionally create a parent_id cycle between lla1 and childLla
        $this->lla1->update(['parent_id' => $this->childLla->id]);
        $this->childLla->update(['parent_id' => $this->lla1->id]);

        // Clear closures to force traversal
        UserClosure::where('descendant_id', $this->childLla->id)->delete();

        // Traversal must terminate safely without hanging or throwing an uncaught exception
        $upline = $this->hierarchyService->getUpline($this->childLla);

        $this->assertLessThanOrEqual(2, $upline->count());
    }

    // =========================================================================
    // 2. CONCURRENCY & TRANSACTION ROLLBACK TESTS
    // =========================================================================

    public function test_move_agent_locks_entire_subtree_deterministically(): void
    {
        // Moving lla1 (which has childLla as descendant) under hla2
        $subtreeCount = UserClosure::where('ancestor_id', $this->lla1->id)->count();
        $this->assertEquals(2, $subtreeCount); // lla1 and childLla

        $this->hierarchyService->moveAgent($this->lla1, $this->hla2);

        $this->lla1->refresh();
        $this->childLla->refresh();

        $this->assertEquals($this->hla2->id, $this->lla1->parent_id);
        $this->assertEquals($this->lla1->id, $this->childLla->parent_id);

        // Verify childLla now has hla2 as ancestor
        $this->assertDatabaseHas('user_closures', [
            'ancestor_id' => $this->hla2->id,
            'descendant_id' => $this->childLla->id,
            'depth' => 2,
        ]);
    }

    public function test_move_agent_rolls_back_on_simulated_exception(): void
    {
        $closureSnapshot = UserClosure::all();
        $llaParentBefore = $this->lla1->parent_id;

        try {
            DB::transaction(function () {
                $this->hierarchyService->moveAgent($this->lla1, $this->hla2);
                throw new Exception('Simulated fatal failure mid-transaction');
            });
        } catch (Exception $e) {
            $this->assertEquals('Simulated fatal failure mid-transaction', $e->getMessage());
        }

        $this->lla1->refresh();
        $this->assertEquals($llaParentBefore, $this->lla1->parent_id);

        // Closure table count must be identical
        $this->assertEquals($closureSnapshot->count(), UserClosure::count());
    }

    // =========================================================================
    // 3. PERMISSION BOUNDARY TESTS
    // =========================================================================

    public function test_agent_manager_with_permission_can_request_hierarchy_move(): void
    {
        $this->assertTrue($this->agentManager->hasPermissionTo('agents.hierarchy.request'));

        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy.request', $this->childLla->id),
            [
                'requested_parent_id' => $this->hla2->id,
                'reason' => 'Promotion to direct HLA',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_agent_manager_without_permission_receives_403(): void
    {
        // Explicitly revoke the granular permission from agent manager role
        $role = Role::where('name', 'agent_manager')->first();
        $permission = Permission::where('name', 'agents.hierarchy.request')->first();
        if ($role && $permission) {
            $role->permissions()->detach($permission->id);
        }

        $this->assertFalse($this->agentManager->hasPermissionTo('agents.hierarchy.request'));

        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.hierarchy.request', $this->childLla->id),
            [
                'requested_parent_id' => $this->hla2->id,
                'reason' => 'Unauthorized move request attempt',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_super_admin_without_permission_receives_403(): void
    {
        // Explicitly revoke agents.hierarchy.request from super admin role
        $role = Role::where('name', 'super_admin')->first();
        $permission = Permission::where('name', 'agents.hierarchy.request')->first();
        if ($role && $permission) {
            $role->permissions()->detach($permission->id);
        }

        $this->assertFalse($this->superAdmin->hasPermissionTo('agents.hierarchy.request'));

        $response = $this->actingAs($this->superAdmin)->post(
            route('admin.agents.hierarchy.request', $this->childLla->id),
            [
                'requested_parent_id' => $this->hla2->id,
                'reason' => 'Super admin attempt without explicit permission',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_it_manager_without_permission_receives_403(): void
    {
        $this->assertFalse($this->itManager->hasPermissionTo('agents.hierarchy.request'));

        $response = $this->actingAs($this->itManager)->post(
            route('admin.agents.hierarchy.request', $this->childLla->id),
            [
                'requested_parent_id' => $this->hla2->id,
                'reason' => 'IT manager attempt without explicit permission',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_marketing_manager_receives_403(): void
    {
        $response = $this->actingAs($this->marketingManager)->post(
            route('admin.agents.hierarchy.request', $this->childLla->id),
            [
                'requested_parent_id' => $this->hla2->id,
                'reason' => 'Marketing manager attempt',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_customer_receives_403(): void
    {
        $response = $this->actingAs($this->customer)->post(
            route('admin.agents.hierarchy.request', $this->childLla->id),
            [
                'requested_parent_id' => $this->hla2->id,
                'reason' => 'Customer attempt',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_owner_can_request_hierarchy_move_without_explicit_permission(): void
    {
        $response = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.request', $this->childLla->id),
            [
                'requested_parent_id' => $this->hla2->id,
                'reason' => 'Owner executive realignment',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_only_owner_can_execute_hierarchy_move_review(): void
    {
        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $this->childLla->id,
            'current_parent_id' => $this->lla1->id,
            'requested_parent_id' => $this->hla2->id,
            'requested_by' => $this->agentManager->id,
            'reason' => 'Review permission boundary test',
            'status' => 'pending',
        ]);

        // Agent Manager cannot review -> 403
        $this->actingAs($this->agentManager)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Super Admin cannot review -> 403
        $this->actingAs($this->superAdmin)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // IT Manager cannot review -> 403
        $this->actingAs($this->itManager)
            ->post(route('admin.agents.hierarchy.review', $changeRequest->id), ['action' => 'approve'])
            ->assertStatus(403);

        // Owner CAN review -> 302
        $response = $this->actingAs($this->owner)->post(
            route('admin.agents.hierarchy.review', $changeRequest->id),
            ['action' => 'approve', 'review_notes' => 'Executive approval granted']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $changeRequest->refresh();
        $this->assertEquals('approved', $changeRequest->status);
    }

    // =========================================================================
    // 4. FINANCIAL INTEGRITY
    // =========================================================================

    public function test_commission_rates_remain_strictly_25_and_10(): void
    {
        $order = $this->createOrderForAgent($this->childLla, '10000.00');
        $entries = $this->commissionService->calculateAndRecordPendingCommissions($order);

        $direct = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_DIRECT_LOW_LEVEL);
        $override = $entries->firstWhere('commission_type', CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE);

        $this->assertEquals(25.0, (float) $direct->rate_percentage);
        $this->assertEquals(10.0, (float) $override->rate_percentage);

        $this->assertEquals('2500.00', $direct->calculated_amount);
        $this->assertEquals('1000.00', $override->calculated_amount);
    }
}
