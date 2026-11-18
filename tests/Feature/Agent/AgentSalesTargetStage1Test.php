<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AgentAnalyticsService;
use App\Services\AgentHierarchyService;
use App\Services\AgentSalesTargetService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AgentSalesTargetStage1Test extends TestCase
{
    use RefreshDatabase;

    private AgentSalesTargetService $targetService;
    private AgentHierarchyService $hierarchyService;
    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $hla;
    private User $lla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->targetService = app(AgentSalesTargetService::class);
        $this->hierarchyService = app(AgentHierarchyService::class);

        // 1. Business Owner
        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);
        $this->owner->syncRole('owner');

        // 2. Super Admin
        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);
        $this->superAdmin->syncRole('super_admin');

        // 3. Agent Manager
        $this->agentManager = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->agentManager->syncRole('agent_manager');

        // 4. High-Level Agent (HLA)
        $this->hla = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'STAGE1HLA01',
        ]);
        AgentProfile::create([
            'user_id' => $this->hla->id,
            'nic' => '199011113333',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
        $this->hierarchyService->attachAgent($this->hla, null);

        // 5. Low-Level Agent (LLA) under HLA
        $this->lla = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'STAGE1LLA02',
        ]);
        AgentProfile::create([
            'user_id' => $this->lla->id,
            'nic' => '199222224444',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
        $this->hierarchyService->attachAgent($this->lla, $this->hla);
    }

    public function test_owner_cannot_bypass_historical_locking_via_update_target(): void
    {
        $closedTarget = AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2025-01',
            'sales_target' => '200000.00',
            'created_by' => $this->owner->id,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Targets in closed historical periods are locked.');

        $this->targetService->updateTarget($closedTarget, [
            'sales_target' => '300000.00',
            'reason' => 'Owner attempting unauthorized direct update',
        ], $this->owner);
    }

    public function test_owner_can_update_closed_target_only_via_override_historical_target(): void
    {
        $closedTarget = AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2025-02',
            'sales_target' => '150000.00',
            'created_by' => $this->agentManager->id,
        ]);

        $updated = $this->targetService->overrideHistoricalTarget(
            $closedTarget,
            '250000.00',
            'Board-mandated retroactive annual review adjustment',
            $this->owner
        );

        $this->assertEquals('250000.00', $updated->sales_target);
        $this->assertDatabaseHas('agent_sales_targets', [
            'id' => $closedTarget->id,
            'sales_target' => '250000.00',
        ]);

        $log = AuditLog::where('action', 'agent_sales_target_historical_override')
            ->where('auditable_id', $closedTarget->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->owner->id, $log->user_id);
        $this->assertTrue($log->new_values['is_historical_override']);
        $this->assertEquals('Board-mandated retroactive annual review adjustment', $log->new_values['override_reason']);
    }

    public function test_closed_historical_target_creation_guard_and_owner_override_creation(): void
    {
        // 1. Standard createTarget() rejects closed historical month
        try {
            $this->targetService->createTarget([
                'agent_id' => $this->lla->id,
                'target_month' => '2025-03',
                'sales_target' => '180000.00',
            ], $this->owner);
            $this->fail('createTarget() should reject closed historical months.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Standard target creation is not permitted for closed historical months', $e->getMessage());
        }

        // 2. Dedicated createHistoricalOverrideTarget() succeeds for Owner
        $created = $this->targetService->createHistoricalOverrideTarget([
            'agent_id' => $this->lla->id,
            'target_month' => '2025-03',
            'sales_target' => '180000.00',
        ], 'Retroactive target establishment following audit settlement', $this->owner);

        $this->assertNotNull($created);
        $this->assertDatabaseHas('agent_sales_targets', [
            'agent_id' => $this->lla->id,
            'target_month' => '2025-03',
            'sales_target' => '180000.00',
            'created_by' => $this->owner->id,
        ]);

        $log = AuditLog::where('action', 'agent_sales_target_historical_created')
            ->where('auditable_id', $created->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->new_values['is_historical_override']);
    }

    public function test_historical_creation_duplicate_protection(): void
    {
        AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2025-04',
            'sales_target' => '220000.00',
            'created_by' => $this->owner->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Use overrideHistoricalTarget() to adjust an existing target.');

        $this->targetService->createHistoricalOverrideTarget([
            'agent_id' => $this->lla->id,
            'target_month' => '2025-04',
            'sales_target' => '250000.00',
        ], 'Attempting duplicate creation', $this->owner);
    }

    public function test_historical_override_methods_reject_current_or_future_months(): void
    {
        // 1. overrideHistoricalTarget() rejects future target
        $futureTarget = AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2027-01',
            'sales_target' => '300000.00',
            'created_by' => $this->owner->id,
        ]);

        try {
            $this->targetService->overrideHistoricalTarget(
                $futureTarget,
                '350000.00',
                'Improper override usage on future month',
                $this->owner
            );
            $this->fail('overrideHistoricalTarget() must reject future months.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Historical override is restricted to closed past months', $e->getMessage());
        }

        // 2. createHistoricalOverrideTarget() rejects future target
        try {
            $this->targetService->createHistoricalOverrideTarget([
                'agent_id' => $this->lla->id,
                'target_month' => '2027-02',
                'sales_target' => '300000.00',
            ], 'Improper override creation on future month', $this->owner);
            $this->fail('createHistoricalOverrideTarget() must reject future months.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Historical creation is restricted to closed past months', $e->getMessage());
        }
    }

    public function test_closed_month_override_denied_to_super_admin_and_agent_manager(): void
    {
        $closedTarget = AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2025-05',
            'sales_target' => '100000.00',
            'created_by' => $this->owner->id,
        ]);

        // Super Admin denied
        try {
            $this->targetService->overrideHistoricalTarget(
                $closedTarget,
                '120000.00',
                'Super admin override attempt',
                $this->superAdmin
            );
            $this->fail('Super Admin must be denied historical override.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Only the Business Owner possesses historical override authority', $e->getMessage());
        }

        // Agent Manager denied
        try {
            $this->targetService->overrideHistoricalTarget(
                $closedTarget,
                '120000.00',
                'Agent manager override attempt',
                $this->agentManager
            );
            $this->fail('Agent Manager must be denied historical override.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Only the Business Owner possesses historical override authority', $e->getMessage());
        }
    }

    public function test_mandatory_adjustment_reason_validation(): void
    {
        $target = AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2027-03',
            'sales_target' => '100000.00',
            'created_by' => $this->agentManager->id,
        ]);

        // Empty reason rejected
        try {
            $this->targetService->updateTarget($target, [
                'sales_target' => '120000.00',
                'reason' => '',
            ], $this->agentManager);
            $this->fail('Empty reason must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('A valid adjustment reason (minimum 5 characters) is required', $e->getMessage());
        }

        // Short reason rejected
        try {
            $this->targetService->updateTarget($target, [
                'sales_target' => '120000.00',
                'reason' => 'Tiny',
            ], $this->agentManager);
            $this->fail('Reason < 5 chars must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('minimum 5 characters', $e->getMessage());
        }

        // Whitespace-only reason rejected
        try {
            $this->targetService->updateTarget($target, [
                'sales_target' => '120000.00',
                'reason' => '     ',
            ], $this->agentManager);
            $this->fail('Whitespace-only reason must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('minimum 5 characters', $e->getMessage());
        }
    }

    public function test_management_update_transitions_provenance_to_management_controlled(): void
    {
        // Target created by HLA for downline LLA
        $target = AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2027-04',
            'sales_target' => '100000.00',
            'created_by' => $this->hla->id,
        ]);
        AuditLog::create([
            'user_id' => $this->hla->id,
            'action' => 'agent_sales_target_hla_assigned',
            'auditable_type' => AgentSalesTarget::class,
            'auditable_id' => $target->id,
            'new_values' => ['sales_target' => '100000.00'],
        ]);

        $this->assertFalse($this->targetService->isTargetManagementControlled($target));

        // Management updates target
        $this->targetService->updateTarget($target, [
            'sales_target' => '150000.00',
            'reason' => 'Management intervention for regional growth',
        ], $this->agentManager);

        $this->assertTrue($this->targetService->isTargetManagementControlled($target));

        // HLA attempts to update now -> rejected!
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This target is under management control and cannot be modified by a High-Level Agent.');

        $this->targetService->updateTarget($target, [
            'sales_target' => '160000.00',
            'reason' => 'HLA attempting to override management decision',
        ], $this->hla);
    }

    public function test_missing_legacy_provenance_fails_closed_for_hla(): void
    {
        // Target with HLA creator but zero audit logs (legacy state)
        $target = AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2027-05',
            'sales_target' => '100000.00',
            'created_by' => $this->hla->id,
        ]);

        $this->assertTrue($this->targetService->isTargetManagementControlled($target), 'Missing provenance must fail closed as Management-Controlled.');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This target is under management control and cannot be modified by a High-Level Agent.');

        $this->targetService->updateTarget($target, [
            'sales_target' => '120000.00',
            'reason' => 'HLA attempting update on legacy target',
        ], $this->hla);
    }

    public function test_verified_hla_target_with_clean_audit_history_allows_hla_update(): void
    {
        // Target created with verified HLA assignment audit action
        $target = AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2027-06',
            'sales_target' => '100000.00',
            'created_by' => $this->hla->id,
        ]);
        AuditLog::create([
            'user_id' => $this->hla->id,
            'action' => 'agent_sales_target_hla_assigned',
            'auditable_type' => AgentSalesTarget::class,
            'auditable_id' => $target->id,
            'new_values' => ['sales_target' => '100000.00'],
        ]);

        $this->assertFalse($this->targetService->isTargetManagementControlled($target));

        // HLA update succeeds
        $updated = $this->targetService->updateTarget($target, [
            'sales_target' => '125000.00',
            'reason' => 'HLA quarterly target revision for downline',
        ], $this->hla);

        $this->assertEquals('125000.00', $updated->sales_target);
    }

    public function test_eloquent_audit_log_immutability_event_guards(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->owner->id,
            'action' => 'test_audit_event',
            'auditable_type' => AgentSalesTarget::class,
            'auditable_id' => 999,
            'new_values' => ['test' => 'data'],
        ]);

        // Attempt update -> DomainException
        try {
            $log->update(['action' => 'tampered_action']);
            $this->fail('AuditLog update must throw DomainException.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('AuditLog records are strictly immutable and cannot be updated', $e->getMessage());
        }

        // Attempt delete -> DomainException
        try {
            $log->delete();
            $this->fail('AuditLog delete must throw DomainException.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('AuditLog records are strictly immutable and cannot be deleted', $e->getMessage());
        }

        // Verify row unchanged in database
        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'test_audit_event',
        ]);
    }

    public function test_string_based_lkr_formatting_preserves_exact_amounts_without_float_casting(): void
    {
        // 1. Standard minimum
        $this->assertEquals('LKR 0.01', AgentAnalyticsService::formatExactLkr('0.01'));

        // 2. Standard 250k
        $this->assertEquals('LKR 250,000.00', AgentAnalyticsService::formatExactLkr('250000.00'));

        // 3. Schema max
        $this->assertEquals('LKR 999,999,999.99', AgentAnalyticsService::formatExactLkr('999999999.99'));

        // 4. Large number exceeding 32-bit int bounds without overflow
        $largeAmount = '123456789012345.67';
        $this->assertEquals('LKR 123,456,789,012,345.67', AgentAnalyticsService::formatExactLkr($largeAmount));
    }

    public function test_target_history_endpoint_returns_paginated_descending_audit_records(): void
    {
        $target = AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => '2027-07',
            'sales_target' => '200000.00',
            'created_by' => $this->agentManager->id,
        ]);

        // Seed 3 audit entries
        for ($i = 1; $i <= 3; $i++) {
            AuditLog::create([
                'user_id' => $this->agentManager->id,
                'action' => 'agent_sales_target_updated',
                'auditable_type' => AgentSalesTarget::class,
                'auditable_id' => $target->id,
                'new_values' => ['sales_target' => (string) (200000 + ($i * 10000)), 'reason' => "Adjustment {$i}"],
            ]);
        }

        $response = $this->actingAs($this->agentManager)
            ->getJson("/admin/agents/analytics/targets/{$target->id}/history");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
        // Descending ID order
        $this->assertTrue($data[0]['id'] > $data[1]['id']);
        $this->assertTrue($data[1]['id'] > $data[2]['id']);
    }
}

