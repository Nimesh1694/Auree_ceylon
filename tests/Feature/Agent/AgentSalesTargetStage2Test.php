<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentHierarchyService;
use App\Services\AgentSalesTargetService;
use App\Services\PeriodBoundaryService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AgentSalesTargetStage2Test extends TestCase
{
    use RefreshDatabase;

    private User $hla1;
    private User $hla2;
    private User $llaDirect;
    private User $llaIndirect;
    private User $llaCrossTree;
    private User $owner;
    private User $agentManager;
    private User $customer;
    private AgentHierarchyService $hierarchyService;
    private AgentSalesTargetService $targetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->hierarchyService = app(AgentHierarchyService::class);
        $this->targetService = app(AgentSalesTargetService::class);

        // 1. Business Owner
        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'email' => 'owner.stage2@example.com',
        ]);

        // 2. Agent Manager
        $this->agentManager = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
            'email' => 'manager.stage2@example.com',
        ]);
        $this->agentManager->syncRole('agent_manager');

        // 3. Customer
        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // 4. Primary High-Level Agent (HLA 1)
        $this->hla1 = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'HLA1STAGE2',
        ]);
        $this->createAgentProfile($this->hla1);
        $this->hierarchyService->attachAgent($this->hla1, null);

        // 5. Direct Low-Level Agent under HLA 1 (depth = 1)
        $this->llaDirect = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'LLADIR001',
        ]);
        $this->createAgentProfile($this->llaDirect);
        $this->hierarchyService->attachAgent($this->llaDirect, $this->hla1);

        // 6. Indirect Low-Level Agent under LLA Direct (depth = 2 under HLA 1)
        $this->llaIndirect = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'LLAINDIR02',
        ]);
        $this->createAgentProfile($this->llaIndirect);
        $this->hierarchyService->attachAgent($this->llaIndirect, $this->llaDirect);

        // 7. Secondary High-Level Agent (HLA 2, separate tree)
        $this->hla2 = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'HLA2STAGE2',
        ]);
        $this->createAgentProfile($this->hla2);
        $this->hierarchyService->attachAgent($this->hla2, null);

        // 8. Cross-tree Low-Level Agent under HLA 2
        $this->llaCrossTree = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'LLACROSS03',
        ]);
        $this->createAgentProfile($this->llaCrossTree);
        $this->hierarchyService->attachAgent($this->llaCrossTree, $this->hla2);
    }

    private function createAgentProfile(User $user): void
    {
        AgentProfile::create([
            'user_id' => $user->id,
            'nic' => '199' . rand(100000000, 999999999),
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
    }

    // =========================================================================
    // 1. BASIC ASSIGNMENT & HIERARCHY TESTS
    // =========================================================================

    public function test_hla_can_assign_target_to_direct_lla_descendant(): void
    {
        $response = $this->actingAs($this->hla1)->post('/agent/team/targets', [
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2027-04',
            'sales_target' => '150000.00',
            'reason' => 'Q2 quota assignment for direct downline member',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('agent_sales_targets', [
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2027-04',
            'sales_target' => '150000.00',
            'created_by' => $this->hla1->id,
        ]);

        $target = AgentSalesTarget::where('agent_id', $this->llaDirect->id)->where('target_month', '2027-04')->firstOrFail();

        $audit = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target->id)
            ->where('action', 'agent_sales_target_hla_assigned')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->hla1->id, $audit->user_id);
        $this->assertEquals('Q2 quota assignment for direct downline member', $audit->new_values['reason']);
        $this->assertFalse($this->targetService->isTargetManagementControlled($target));
    }

    public function test_hla_can_assign_target_to_indirect_lla_descendant(): void
    {
        // HLA 1 assigning target to LLA Indirect (depth = 2)
        $target = $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaIndirect->id,
            '2027-05',
            '200000.00',
            'Second-tier downline growth benchmark'
        );

        $this->assertInstanceOf(AgentSalesTarget::class, $target);
        $this->assertEquals($this->llaIndirect->id, $target->agent_id);
        $this->assertEquals('200000.00', $target->sales_target);
        $this->assertEquals($this->hla1->id, $target->created_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_sales_target_hla_assigned',
            'auditable_id' => $target->id,
            'user_id' => $this->hla1->id,
        ]);
    }

    public function test_cross_tree_access_rejected(): void
    {
        // Service level check
        $this->expectException(AuthorizationException::class);
        $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaCrossTree->id, // belongs to HLA 2
            '2027-06',
            '100000.00',
            'Unauthorized attempt on other tree'
        );

        // HTTP level check
        $response = $this->actingAs($this->hla1)->post('/agent/team/targets', [
            'agent_id' => $this->llaCrossTree->id,
            'target_month' => '2027-06',
            'sales_target' => '100000.00',
            'reason' => 'Unauthorized attempt on other tree',
        ]);

        $response->assertForbidden();
    }

    public function test_peer_hla_assignment_rejected(): void
    {
        // HLA 1 attempting to assign target to HLA 2 (peer agent)
        $this->expectException(AuthorizationException::class);
        $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->hla2->id,
            '2027-07',
            '500000.00',
            'Peer HLA assignment must be rejected'
        );
    }

    public function test_self_assignment_rejected(): void
    {
        // HLA 1 attempting to assign target to themselves
        $this->expectException(AuthorizationException::class);
        $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->hla1->id,
            '2027-08',
            '300000.00',
            'Self assignment must be rejected'
        );
    }

    // =========================================================================
    // 2. RECIPIENT STATUS VERIFICATION (MANDATORY REQUIREMENT 3)
    // =========================================================================

    public function test_recipient_status_pending_approval_inactive_rejected(): void
    {
        // Set LLA Direct to pending (inactive)
        $this->llaDirect->status = UserStatus::PENDING;
        $this->llaDirect->save();

        // 1. Service-level check
        try {
            $this->targetService->assignDownlineTarget(
                $this->hla1,
                $this->llaDirect->id,
                '2027-09',
                '100000.00',
                'Pending approval recipient must fail'
            );
            $this->fail('Expected AuthorizationException for inactive recipient');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Targets can only be assigned to active agents', $e->getMessage());
        }

        // 2. HTTP FormRequest check
        $response = $this->actingAs($this->hla1)->post('/agent/team/targets', [
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2027-09',
            'sales_target' => '100000.00',
            'reason' => 'Pending approval recipient HTTP check',
        ]);
        $response->assertForbidden();
    }

    public function test_recipient_status_suspended_rejected(): void
    {
        // Suspend LLA Direct (Account Status = SUSPENDED)
        $this->llaDirect->status = UserStatus::SUSPENDED;
        $this->llaDirect->save();

        // 1. Service-level check
        try {
            $this->targetService->assignDownlineTarget(
                $this->hla1,
                $this->llaDirect->id,
                '2027-09',
                '100000.00',
                'Suspended recipient must fail'
            );
            $this->fail('Expected AuthorizationException for suspended recipient');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Targets can only be assigned to active agents', $e->getMessage());
        }

        // 2. HTTP FormRequest check
        $response = $this->actingAs($this->hla1)->post('/agent/team/targets', [
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2027-09',
            'sales_target' => '100000.00',
            'reason' => 'Suspended recipient HTTP check',
        ]);
        $response->assertForbidden();
    }

    public function test_recipient_kyc_rejected_with_active_account_rejected(): void
    {
        // Account is ACTIVE, but KYC review status is REJECTED (distinct verification status)
        $this->llaDirect->status = UserStatus::ACTIVE;
        $this->llaDirect->save();
        $this->llaDirect->agentProfile->update(['kyc_status' => 'rejected']);

        // 1. Service-level check
        try {
            $this->targetService->assignDownlineTarget(
                $this->hla1,
                $this->llaDirect->id,
                '2027-09',
                '100000.00',
                'Active account with rejected KYC must fail'
            );
            $this->fail('Expected AuthorizationException for KYC-rejected recipient');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Targets cannot be assigned to agents with rejected KYC verification status', $e->getMessage());
        }

        // 2. HTTP FormRequest check
        $response = $this->actingAs($this->hla1)->post('/agent/team/targets', [
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2027-09',
            'sales_target' => '100000.00',
            'reason' => 'KYC-rejected recipient HTTP check',
        ]);
        $response->assertForbidden();
    }

    public function test_recipient_active_non_lla_customer_rejected(): void
    {
        // Attempting to assign target to an active customer
        try {
            $this->targetService->assignDownlineTarget(
                $this->hla1,
                $this->customer->id,
                '2027-09',
                '100000.00',
                'Customer recipient must be rejected'
            );
            $this->fail('Expected AuthorizationException for customer recipient');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Targets can only be assigned to Low-Level Agents', $e->getMessage());
        }

        $response = $this->actingAs($this->hla1)->post('/agent/team/targets', [
            'agent_id' => $this->customer->id,
            'target_month' => '2027-09',
            'sales_target' => '100000.00',
            'reason' => 'Customer recipient HTTP check',
        ]);
        $response->assertForbidden();
    }

    public function test_recipient_active_non_lla_staff_rejected(): void
    {
        // Attempting to assign target to an active staff member (Agent Manager)
        try {
            $this->targetService->assignDownlineTarget(
                $this->hla1,
                $this->agentManager->id,
                '2027-09',
                '100000.00',
                'Staff recipient must be rejected'
            );
            $this->fail('Expected AuthorizationException for staff recipient');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Targets can only be assigned to Low-Level Agents', $e->getMessage());
        }

        $response = $this->actingAs($this->hla1)->post('/agent/team/targets', [
            'agent_id' => $this->agentManager->id,
            'target_month' => '2027-09',
            'sales_target' => '100000.00',
            'reason' => 'Staff recipient HTTP check',
        ]);
        $response->assertForbidden();
    }

    public function test_active_lla_outside_hierarchy_rejected(): void
    {
        // LLA CrossTree is an active LLA, but attached under HLA 2
        try {
            $this->targetService->assignDownlineTarget(
                $this->hla1,
                $this->llaCrossTree->id,
                '2027-09',
                '100000.00',
                'Cross-tree LLA must fail closure check'
            );
            $this->fail('Expected AuthorizationException for cross-tree LLA');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Target recipient is not within your downline hierarchy', $e->getMessage());
        }

        $response = $this->actingAs($this->hla1)->post('/agent/team/targets', [
            'agent_id' => $this->llaCrossTree->id,
            'target_month' => '2027-09',
            'sales_target' => '100000.00',
            'reason' => 'Cross-tree LLA HTTP check',
        ]);
        $response->assertForbidden();
    }

    public function test_inactive_hla_cannot_assign_or_update(): void
    {
        // Suspend HLA 1
        $this->hla1->status = UserStatus::SUSPENDED;
        $this->hla1->save();

        $this->expectException(AuthorizationException::class);
        $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2027-10',
            '100000.00',
            'Suspended HLA actor must be rejected'
        );
    }

    public function test_inactive_management_actor_rejected_from_target_mutations(): void
    {
        // Suspend Agent Manager
        $this->agentManager->status = UserStatus::SUSPENDED;
        $this->agentManager->save();

        // 1. Inactive manager cannot create targets
        try {
            $this->targetService->createTarget([
                'agent_id' => $this->llaDirect->id,
                'target_month' => '2028-11',
                'sales_target' => '100000.00',
            ], $this->agentManager);
            $this->fail('Expected AuthorizationException for suspended manager createTarget');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Only active actors are authorized', $e->getMessage());
        }

        // Create an active target by owner to test update
        $target = $this->targetService->createTarget([
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2028-11',
            'sales_target' => '100000.00',
        ], $this->owner);

        // 2. Inactive manager cannot update targets
        try {
            $this->targetService->updateTarget($target, [
                'sales_target' => '110000.00',
                'reason' => 'Suspended manager update attempt',
            ], $this->agentManager);
            $this->fail('Expected AuthorizationException for suspended manager updateTarget');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('not authorized', $e->getMessage());
        }

        // 3. Inactive manager cannot pass assertActorCanModifyTarget
        try {
            $this->targetService->assertActorCanModifyTarget($target, $this->agentManager);
            $this->fail('Expected AuthorizationException for assertActorCanModifyTarget');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Inactive actors are not authorized', $e->getMessage());
        }
    }

    public function test_business_owner_active_status_immutability_and_non_owner_override_rejection(): void
    {
        $pastMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->subMonths(2)->format('Y-m');

        // 1. Business Owner active status is strictly immutable at the model level
        try {
            $this->owner->status = UserStatus::SUSPENDED;
            $this->owner->save();
            $this->fail('Expected AuthorizationException when attempting to suspend Business Owner');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('The Business Owner role, active status, and business authority are immutable', $e->getMessage());
        }

        // 2. Non-owner management actor (Super Admin / Agent Manager) is strictly rejected from historical override
        try {
            $this->targetService->createHistoricalOverrideTarget([
                'agent_id' => $this->llaDirect->id,
                'target_month' => $pastMonth,
                'sales_target' => '100000.00',
            ], 'Agent manager attempting historical override', $this->agentManager);
            $this->fail('Expected AuthorizationException for non-owner historical override');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Business Owner possesses historical override authority', $e->getMessage());
        }
    }

    // =========================================================================
    // 3. CONCURRENCY & UNIQUE CONSTRAINT VERIFICATION (MANDATORY REQUIREMENT 2)
    // =========================================================================

    public function test_concurrent_hla_assignments_prevent_duplicates_via_unique_constraint(): void
    {
        // First assignment succeeds
        $target1 = $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2028-05',
            '100000.00',
            'First concurrent request'
        );
        $this->assertNotNull($target1);

        // Second assignment colliding on same (agent_id, target_month)
        try {
            $this->targetService->assignDownlineTarget(
                $this->hla1,
                $this->llaDirect->id,
                '2028-05',
                '120000.00',
                'Second concurrent request colliding'
            );
            $this->fail('Expected collision to throw InvalidArgumentException or QueryException');
        } catch (\Throwable $e) {
            $this->assertTrue(
                $e instanceof InvalidArgumentException ||
                $e instanceof \Illuminate\Database\QueryException
            );
        }

        // Verify exactly one target record exists
        $count = AgentSalesTarget::where('agent_id', $this->llaDirect->id)
            ->where('target_month', '2028-05')
            ->count();
        $this->assertEquals(1, $count);

        // Verify zero orphaned audit records
        $auditCount = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target1->id)
            ->where('action', 'agent_sales_target_hla_assigned')
            ->count();
        $this->assertEquals(1, $auditCount);
    }

    public function test_transaction_rollback_when_audit_creation_fails(): void
    {
        // Temporarily hook into AuditLog creating event to simulate failure
        AuditLog::saving(function () {
            throw new \RuntimeException('Simulated audit persistence infrastructure failure');
        });

        try {
            $this->targetService->assignDownlineTarget(
                $this->hla1,
                $this->llaDirect->id,
                '2028-06',
                '100000.00',
                'Assignment where audit fails'
            );
            $this->fail('Expected simulated audit failure to throw RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Simulated audit persistence infrastructure failure', $e->getMessage());
        } finally {
            // Clear the event listener
            AuditLog::flushEventListeners();
        }

        // Verify atomic rollback: neither target nor audit log persisted
        $this->assertDatabaseMissing('agent_sales_targets', [
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2028-06',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'agent_sales_target_hla_assigned',
        ]);
    }

    // =========================================================================
    // 4. PROVENANCE & TRANSACTION UPDATE SECURITY (MANDATORY REQUIREMENTS 4 & 5)
    // =========================================================================

    public function test_management_controlled_target_update_rejected_for_hla(): void
    {
        // Target created by Agent Manager
        $target = $this->targetService->createTarget([
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2027-11',
            'sales_target' => '175000.00',
        ], $this->agentManager);

        $this->assertTrue($this->targetService->isTargetManagementControlled($target));

        // HLA attempts to update via HTTP route -> 403 Forbidden
        $response = $this->actingAs($this->hla1)->put("/agent/team/targets/{$target->id}", [
            'sales_target' => '200000.00',
            'reason' => 'HLA trying to alter management target',
        ]);

        $response->assertForbidden();

        // Target remains unaltered in database
        $this->assertDatabaseHas('agent_sales_targets', [
            'id' => $target->id,
            'sales_target' => '175000.00',
        ]);
    }

    public function test_hla_controlled_target_update_success_and_subsequent_updates(): void
    {
        // Target assigned by HLA 1
        $target = $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2027-12',
            '100000.00',
            'Initial HLA benchmark'
        );

        $this->assertFalse($this->targetService->isTargetManagementControlled($target));

        // First update by HLA 1
        $response = $this->actingAs($this->hla1)->put("/agent/team/targets/{$target->id}", [
            'sales_target' => '130000.00',
            'reason' => 'Performance revision upward based on mid-quarter results',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $target->refresh();
        $this->assertEquals('130000.00', $target->sales_target);

        // Verify audit log has HLA updated action
        $audit1 = AuditLog::where('auditable_id', $target->id)
            ->where('action', 'agent_sales_target_hla_updated')
            ->first();
        $this->assertNotNull($audit1);
        $this->assertEquals($this->hla1->id, $audit1->user_id);

        // Target is STILL not management-controlled
        $this->assertFalse($this->targetService->isTargetManagementControlled($target));

        // Second update by HLA 1 succeeds cleanly
        $updatedTarget2 = $this->targetService->updateDownlineTarget($this->hla1, $target, [
            'sales_target' => '145000.00',
            'reason' => 'Final monthly target adjustment for downline',
        ]);

        $this->assertEquals('145000.00', $updatedTarget2->sales_target);
        $this->assertFalse($this->targetService->isTargetManagementControlled($target));
    }

    public function test_audit_provenance_management_action_permanently_locks_target_from_hla(): void
    {
        // 1. Target assigned by HLA 1
        $target = $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2028-07',
            '100000.00',
            'Initial HLA assignment'
        );
        $this->assertFalse($this->targetService->isTargetManagementControlled($target));

        // 2. Management (Agent Manager) intervenes and updates target
        $this->targetService->updateTarget($target, [
            'sales_target' => '180000.00',
            'reason' => 'Management corporate quota revision',
        ], $this->agentManager);

        // 3. Target has transitioned to Management-Controlled
        $target->refresh();
        $this->assertTrue($this->targetService->isTargetManagementControlled($target));

        // 4. HLA can no longer update target
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This target is under management control and cannot be modified by a High-Level Agent');

        $this->targetService->updateDownlineTarget($this->hla1, $target, [
            'sales_target' => '190000.00',
            'reason' => 'HLA attempting to override management update',
        ]);
    }

    public function test_hla_cannot_tamper_with_target_month_on_update(): void
    {
        $target = $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2028-08',
            '100000.00',
            'Initial valid assignment'
        );

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('High-Level Agents cannot alter target months on existing assignments');

        // HLA attempts to alter target_month
        $this->targetService->updateDownlineTarget($this->hla1, $target, [
            'target_month' => '2028-09',
            'sales_target' => '110000.00',
            'reason' => 'Attempting to change month',
        ]);
    }

    public function test_update_transaction_rechecks_recipient_active_status(): void
    {
        $target = $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2028-09',
            '100000.00',
            'Initial assignment for active LLA'
        );

        // Now recipient gets suspended
        $this->llaDirect->status = UserStatus::SUSPENDED;
        $this->llaDirect->save();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Targets can only be managed for active Low-Level Agents');

        // Update must fail inside transaction
        $this->targetService->updateDownlineTarget($this->hla1, $target, [
            'sales_target' => '120000.00',
            'reason' => 'Attempting update on newly suspended recipient',
        ]);
    }

    public function test_update_transaction_rechecks_hierarchy_closure(): void
    {
        $target = $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2028-10',
            '100000.00',
            'Initial assignment within hierarchy'
        );

        // Sever hierarchy link in user_closures
        UserClosure::where('ancestor_id', $this->hla1->id)
            ->where('descendant_id', $this->llaDirect->id)
            ->delete();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You are not authorized to modify targets for agents outside your downline');

        // Update must fail inside transaction
        $this->targetService->updateDownlineTarget($this->hla1, $target, [
            'sales_target' => '120000.00',
            'reason' => 'Attempting update after hierarchy detachment',
        ]);
    }

    public function test_missing_or_corrupt_provenance_fails_closed(): void
    {
        // Target created without audit log (e.g. legacy/corrupted)
        $target = AgentSalesTarget::create([
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2028-01',
            'sales_target' => '120000.00',
            'created_by' => $this->hla1->id,
        ]);

        // Provenance must fail closed
        $this->assertTrue($this->targetService->isTargetManagementControlled($target));

        // HLA update attempt throws AuthorizationException
        $this->expectException(AuthorizationException::class);
        $this->targetService->updateTarget($target, [
            'sales_target' => '130000.00',
            'reason' => 'Attempting update on legacy target',
        ], $this->hla1);
    }

    public function test_duplicate_target_rejection(): void
    {
        $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2028-02',
            '100000.00',
            'Initial valid assignment'
        );

        // Attempting duplicate month assignment throws InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2028-02',
            '110000.00',
            'Duplicate assignment should fail'
        );
    }

    public function test_non_hla_unauthorized_request_rejection(): void
    {
        // LLA trying to assign target
        $responseLla = $this->actingAs($this->llaDirect)->post('/agent/team/targets', [
            'agent_id' => $this->llaIndirect->id,
            'target_month' => '2028-03',
            'sales_target' => '100000.00',
            'reason' => 'LLA cannot assign targets',
        ]);
        $responseLla->assertForbidden();

        // Customer trying to assign target
        $responseCustomer = $this->actingAs($this->customer)->post('/agent/team/targets', [
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2028-03',
            'sales_target' => '100000.00',
            'reason' => 'Customer cannot assign targets',
        ]);
        $responseCustomer->assertForbidden();

        // Unauthenticated
        auth()->logout();
        $responseGuest = $this->post('/agent/team/targets', [
            'agent_id' => $this->llaDirect->id,
            'target_month' => '2028-03',
            'sales_target' => '100000.00',
            'reason' => 'Guest cannot assign targets',
        ]);
        $responseGuest->assertRedirect('/login');
    }

    public function test_closed_historical_period_rejection(): void
    {
        $currentMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
        $pastMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->subMonths(2)->format('Y-m');

        $this->expectException(AuthorizationException::class);
        $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            $pastMonth,
            '100000.00',
            'Attempt to assign target in closed historical month'
        );
    }

    public function test_mandatory_reason_validation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            '2028-04',
            '100000.00',
            'Bad' // Under 5 characters
        );
    }

    public function test_downline_members_structure_in_analytics_index(): void
    {
        // Assign target for HLA 1's downline LLA
        $currentMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
        $this->targetService->assignDownlineTarget(
            $this->hla1,
            $this->llaDirect->id,
            $currentMonth,
            '160000.00',
            'Target for current active month'
        );

        $response = $this->actingAs($this->hla1)->get('/agent/analytics');
        $response->assertOk();

        // Verify downlineMembers are passed to Inertia
        $response->assertInertia(fn ($page) => $page
            ->component('Agent/Analytics/Index')
            ->has('downlineMembers', 2) // LLA Direct (depth=1) and LLA Indirect (depth=2)
            ->where('downlineMembers.0.id', $this->llaDirect->id)
            ->where('downlineMembers.0.depth', 1)
            ->where('downlineMembers.0.target.sales_target', '160000.00')
            ->where('downlineMembers.0.target.is_management_controlled', false)
            ->where('downlineMembers.0.target.can_edit', true)
            ->where('downlineMembers.1.id', $this->llaIndirect->id)
            ->where('downlineMembers.1.depth', 2)
            ->where('downlineMembers.1.target', null)
        );
    }
}
