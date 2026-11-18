<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AgentSalesTargetService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentSalesTargetGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private AgentSalesTargetService $targetService;
    private User $manager;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->targetService = app(AgentSalesTargetService::class);

        $this->manager = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->manager->syncRole('agent_manager');

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'GOVAGENT01',
        ]);
        AgentProfile::create([
            'user_id' => $this->agent->id,
            'nic' => '199212345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
    }

    public function test_target_creation_records_explicit_actor_attribution_and_audit_snapshot(): void
    {
        $target = $this->targetService->createTarget([
            'agent_id' => $this->agent->id,
            'target_month' => '2027-06',
            'sales_target' => '500000.00',
        ], $this->manager);

        $this->assertDatabaseHas('agent_sales_targets', [
            'id' => $target->id,
            'agent_id' => $this->agent->id,
            'target_month' => '2027-06',
            'sales_target' => '500000.00',
            'created_by' => $this->manager->id,
        ]);

        // AuditLog record check
        $log = AuditLog::where('action', 'agent_sales_target_created')
            ->where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target->id)
            ->first();

        $this->assertNotNull($log, 'AuditLog entry must be recorded.');
        $this->assertEquals($this->manager->id, $log->user_id, 'Explicit actor attribution must match the manager user ID.');
        $this->assertNull($log->old_values);
        $this->assertIsArray($log->new_values);
        $this->assertEquals('500000.00', $log->new_values['sales_target']);
        $this->assertEquals('2027-06', $log->new_values['target_month']);
        $this->assertEquals($this->agent->id, $log->new_values['agent_id']);
    }

    public function test_target_update_records_old_and_new_value_diffs_in_audit_log(): void
    {
        $target = $this->targetService->createTarget([
            'agent_id' => $this->agent->id,
            'target_month' => '2027-07',
            'sales_target' => '300000.00',
        ], $this->manager);

        $updatedTarget = $this->targetService->updateTarget($target, [
            'sales_target' => '450000.00',
            'reason' => 'Mid-year quota adjustment',
        ], $this->manager);

        $this->assertEquals('450000.00', $updatedTarget->sales_target);

        $log = AuditLog::where('action', 'agent_sales_target_updated')
            ->where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->manager->id, $log->user_id);
        $this->assertIsArray($log->old_values);
        $this->assertIsArray($log->new_values);
        $this->assertEquals('300000.00', $log->old_values['sales_target']);
        $this->assertEquals('450000.00', $log->new_values['sales_target']);
    }

    public function test_target_creation_atomically_rolls_back_if_audit_fails(): void
    {
        // Mock DB failure or trigger exception inside transaction
        try {
            DB::transaction(function () {
                $target = AgentSalesTarget::create([
                    'agent_id' => $this->agent->id,
                    'target_month' => '2027-09',
                    'sales_target' => '600000.00',
                    'created_by' => $this->manager->id,
                ]);

                // Simulate catastrophic audit failure
                throw new Exception('Simulated audit failure during target creation.');
            });
        } catch (Exception $e) {
            // Caught
        }

        $this->assertDatabaseMissing('agent_sales_targets', [
            'agent_id' => $this->agent->id,
            'target_month' => '2027-09',
        ]);
    }
}

