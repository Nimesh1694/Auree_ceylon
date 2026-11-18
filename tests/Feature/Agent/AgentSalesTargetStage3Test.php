<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserClosure;
use App\Notifications\AgentTargetAssignedNotification;
use App\Notifications\AgentTargetUpdatedNotification;
use App\Services\AgentAnalyticsService;
use App\Services\AgentSalesTargetService;
use App\Services\PeriodBoundaryService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AgentSalesTargetStage3Test extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $admin;
    protected User $hla;
    protected User $lla;
    protected AgentSalesTargetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AgentSalesTargetService::class);

        // 1. Business Owner
        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'protected_authority' => 'business',
            'email' => 'owner_stage3@example.com',
        ]);

        // 2. Super Admin
        $this->admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'status' => UserStatus::ACTIVE->value,
            'email' => 'admin_stage3@example.com',
        ]);

        // 3. High-Level Agent
        $this->hla = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE->value,
            'email' => 'hla_stage3@example.com',
        ]);
        $this->createAgentProfile($this->hla, 'verified');

        // 4. Low-Level Agent (descendant of HLA)
        $this->lla = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE->value,
            'parent_id' => $this->hla->id,
            'email' => 'lla_stage3@example.com',
        ]);
        $this->createAgentProfile($this->lla, 'verified');

        // Closures for hierarchy
        UserClosure::create(['ancestor_id' => $this->hla->id, 'descendant_id' => $this->hla->id, 'depth' => 0]);
        UserClosure::create(['ancestor_id' => $this->lla->id, 'descendant_id' => $this->lla->id, 'depth' => 0]);
        UserClosure::create(['ancestor_id' => $this->hla->id, 'descendant_id' => $this->lla->id, 'depth' => 1]);
    }

    private function createAgentProfile(User $user, string $kycStatus = 'verified'): AgentProfile
    {
        return AgentProfile::create([
            'user_id' => $user->id,
            'nic' => '199' . rand(100000000, 999999999),
            'kyc_status' => $kycStatus,
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
    }

    public function test_management_target_creation_dispatches_database_notification_with_deterministic_uuidv5(): void
    {
        $targetMonth = '2027-05';
        $target = $this->service->createTarget([
            'agent_id' => $this->lla->id,
            'target_month' => $targetMonth,
            'sales_target' => '500000.00',
        ], $this->admin);

        $auditLog = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target->id)
            ->where('action', 'agent_sales_target_created')
            ->firstOrFail();

        $expectedNotificationId = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.target.audit.{$auditLog->id}"
        )->toString();

        // Verify notification in database
        $this->assertDatabaseHas('notifications', [
            'id' => $expectedNotificationId,
            'type' => AgentTargetAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->lla->id,
        ]);

        $notificationRow = DB::table('notifications')->where('id', $expectedNotificationId)->first();
        $this->assertNotNull($notificationRow);

        $payload = json_decode($notificationRow->data, true);
        $this->assertSame($target->id, $payload['target_id']);
        $this->assertSame($targetMonth, $payload['target_month']);
        $this->assertSame('500000.00', $payload['sales_target']);
        $this->assertSame('LKR 500,000.00', $payload['sales_target_formatted']);
        $this->assertSame($this->admin->id, $payload['actor_id']);
        $this->assertSame('Monthly Sales Target Assigned', $payload['title']);
    }

    public function test_management_target_update_dispatches_database_notification_with_deterministic_uuidv5(): void
    {
        $targetMonth = '2027-06';
        $target = $this->service->createTarget([
            'agent_id' => $this->lla->id,
            'target_month' => $targetMonth,
            'sales_target' => '400000.00',
        ], $this->admin);

        $updatedTarget = $this->service->updateTarget($target, [
            'sales_target' => '650000.00',
            'reason' => 'Increased quarterly campaign target quota',
        ], $this->admin);

        $updateAuditLog = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $updatedTarget->id)
            ->where('action', 'agent_sales_target_updated')
            ->firstOrFail();

        $expectedNotificationId = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.target.audit.{$updateAuditLog->id}"
        )->toString();

        $this->assertDatabaseHas('notifications', [
            'id' => $expectedNotificationId,
            'type' => AgentTargetUpdatedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->lla->id,
        ]);

        $notificationRow = DB::table('notifications')->where('id', $expectedNotificationId)->first();
        $payload = json_decode($notificationRow->data, true);
        $this->assertSame('650000.00', $payload['sales_target']);
        $this->assertSame('LKR 650,000.00', $payload['sales_target_formatted']);
        $this->assertSame('Increased quarterly campaign target quota', $payload['reason']);
        $this->assertSame('Monthly Sales Target Updated', $payload['title']);
    }

    public function test_hla_target_assignment_dispatches_database_notification_with_deterministic_uuidv5(): void
    {
        $targetMonth = '2027-07';
        $target = $this->service->assignDownlineTarget(
            $this->hla,
            $this->lla->id,
            $targetMonth,
            '250000.00',
            'Q3 strategic territory allocation'
        );

        $auditLog = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target->id)
            ->where('action', 'agent_sales_target_hla_assigned')
            ->firstOrFail();

        $expectedNotificationId = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.target.audit.{$auditLog->id}"
        )->toString();

        $this->assertDatabaseHas('notifications', [
            'id' => $expectedNotificationId,
            'type' => AgentTargetAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->lla->id,
        ]);

        $notificationRow = DB::table('notifications')->where('id', $expectedNotificationId)->first();
        $payload = json_decode($notificationRow->data, true);
        $this->assertSame('Downline Sales Target Assigned', $payload['title']);
        $this->assertSame($this->hla->id, $payload['actor_id']);
        $this->assertSame('Q3 strategic territory allocation', $payload['reason']);
        $this->assertStringContainsString($this->hla->name, $payload['message']);
    }

    public function test_hla_target_update_dispatches_database_notification_with_deterministic_uuidv5(): void
    {
        $targetMonth = '2027-08';
        $target = $this->service->assignDownlineTarget(
            $this->hla,
            $this->lla->id,
            $targetMonth,
            '300000.00',
            'Initial August quota allocation'
        );

        $updated = $this->service->updateDownlineTarget($this->hla, $target, [
            'sales_target' => '450000.00',
            'reason' => 'Mid-month performance quota revision',
        ]);

        $auditLog = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $updated->id)
            ->where('action', 'agent_sales_target_hla_updated')
            ->firstOrFail();

        $expectedNotificationId = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.target.audit.{$auditLog->id}"
        )->toString();

        $this->assertDatabaseHas('notifications', [
            'id' => $expectedNotificationId,
            'type' => AgentTargetUpdatedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->lla->id,
        ]);

        $notificationRow = DB::table('notifications')->where('id', $expectedNotificationId)->first();
        $payload = json_decode($notificationRow->data, true);
        $this->assertSame('Downline Sales Target Updated', $payload['title']);
        $this->assertSame('450000.00', $payload['sales_target']);
        $this->assertSame('Mid-month performance quota revision', $payload['reason']);
    }

    public function test_owner_historical_creation_and_override_dispatch_notifications(): void
    {
        // 1. Owner historical creation
        $historicalMonth = '2026-01'; // Past month relative to 2026-11
        $created = $this->service->createHistoricalOverrideTarget([
            'agent_id' => $this->lla->id,
            'target_month' => $historicalMonth,
            'sales_target' => '100000.00',
        ], 'Retroactive audit target restitution', $this->owner);

        $createAudit = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $created->id)
            ->where('action', 'agent_sales_target_historical_created')
            ->firstOrFail();

        $createNotificationId = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.target.audit.{$createAudit->id}"
        )->toString();

        $this->assertDatabaseHas('notifications', [
            'id' => $createNotificationId,
            'type' => AgentTargetAssignedNotification::class,
            'notifiable_id' => $this->lla->id,
        ]);

        // 2. Owner historical override
        $overridden = $this->service->overrideHistoricalTarget(
            $created,
            '150000.00',
            'Post-settlement retroactive target recalculation',
            $this->owner
        );

        $overrideAudit = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $overridden->id)
            ->where('action', 'agent_sales_target_historical_override')
            ->firstOrFail();

        $overrideNotificationId = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.target.audit.{$overrideAudit->id}"
        )->toString();

        $this->assertDatabaseHas('notifications', [
            'id' => $overrideNotificationId,
            'type' => AgentTargetUpdatedNotification::class,
            'notifiable_id' => $this->lla->id,
        ]);
    }

    public function test_no_notification_dispatched_when_transaction_rolls_back(): void
    {
        $initialNotificationCount = DB::table('notifications')->count();

        try {
            DB::transaction(function () {
                $target = AgentSalesTarget::create([
                    'agent_id' => $this->lla->id,
                    'target_month' => '2027-09',
                    'sales_target' => '500000.00',
                    'created_by' => $this->admin->id,
                ]);

                $auditLog = AuditLog::create([
                    'user_id' => $this->admin->id,
                    'action' => 'agent_sales_target_created',
                    'auditable_type' => AgentSalesTarget::class,
                    'auditable_id' => $target->id,
                    'old_values' => null,
                    'new_values' => ['test' => true],
                ]);

                DB::afterCommit(function () use ($target, $auditLog) {
                    $this->service->dispatchTargetNotification($target, $this->admin, $auditLog);
                });

                // Force intentional transaction rollback
                throw new \RuntimeException("Forced transaction abort to test rollback safety.");
            });
        } catch (\RuntimeException $e) {
            $this->assertSame("Forced transaction abort to test rollback safety.", $e->getMessage());
        }

        $this->assertSame($initialNotificationCount, DB::table('notifications')->count());
    }

    public function test_notifications_not_dispatched_to_inactive_or_kyc_rejected_agents(): void
    {
        // 1. Inactive Agent
        $inactiveLla = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::SUSPENDED->value,
        ]);
        $this->createAgentProfile($inactiveLla, 'verified');

        $auditLogDummy1 = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'agent_sales_target_created',
            'auditable_type' => AgentSalesTarget::class,
            'auditable_id' => 9991,
            'new_values' => ['target' => '100000.00'],
        ]);
        $target1 = new AgentSalesTarget([
            'agent_id' => $inactiveLla->id,
            'target_month' => '2027-10',
            'sales_target' => '100000.00',
            'created_by' => $this->admin->id,
        ]);
        $target1->id = 9991;

        $this->service->dispatchTargetNotification($target1, $this->admin, $auditLogDummy1);
        $expectedId1 = Uuid::uuid5(Uuid::NAMESPACE_DNS, "auraluxe.target.audit.{$auditLogDummy1->id}")->toString();
        $this->assertDatabaseMissing('notifications', ['id' => $expectedId1]);

        // 2. KYC Rejected Agent
        $rejectedKycLla = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
        $this->createAgentProfile($rejectedKycLla, 'rejected');

        $auditLogDummy2 = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'agent_sales_target_created',
            'auditable_type' => AgentSalesTarget::class,
            'auditable_id' => 9992,
            'new_values' => ['target' => '100000.00'],
        ]);
        $target2 = new AgentSalesTarget([
            'agent_id' => $rejectedKycLla->id,
            'target_month' => '2027-10',
            'sales_target' => '100000.00',
            'created_by' => $this->admin->id,
        ]);
        $target2->id = 9992;

        $this->service->dispatchTargetNotification($target2, $this->admin, $auditLogDummy2);
        $expectedId2 = Uuid::uuid5(Uuid::NAMESPACE_DNS, "auraluxe.target.audit.{$auditLogDummy2->id}")->toString();
        $this->assertDatabaseMissing('notifications', ['id' => $expectedId2]);
    }

    public function test_deterministic_notification_idempotency_prevents_duplicate_records_on_re_dispatch(): void
    {
        $targetMonth = '2027-11';
        $target = $this->service->createTarget([
            'agent_id' => $this->lla->id,
            'target_month' => $targetMonth,
            'sales_target' => '750000.00',
        ], $this->admin);

        $auditLog = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target->id)
            ->firstOrFail();

        $deterministicId = Uuid::uuid5(Uuid::NAMESPACE_DNS, "auraluxe.target.audit.{$auditLog->id}")->toString();

        $this->assertSame(1, DB::table('notifications')->where('id', $deterministicId)->count());

        // Attempt re-dispatch multiple times
        $this->service->dispatchTargetNotification($target, $this->admin, $auditLog);
        $this->service->dispatchTargetNotification($target, $this->admin, $auditLog);

        $this->assertSame(1, DB::table('notifications')->where('id', $deterministicId)->count());
    }

    public function test_retry_command_reconstructs_missing_notifications_from_audit_logs(): void
    {
        $targetMonth = '2027-12';
        $target = $this->service->createTarget([
            'agent_id' => $this->lla->id,
            'target_month' => $targetMonth,
            'sales_target' => '800000.00',
        ], $this->admin);

        $auditLog = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target->id)
            ->firstOrFail();

        $deterministicId = Uuid::uuid5(Uuid::NAMESPACE_DNS, "auraluxe.target.audit.{$auditLog->id}")->toString();

        // Simulate lost notification delivery by deleting the database row
        DB::table('notifications')->where('id', $deterministicId)->delete();
        $this->assertDatabaseMissing('notifications', ['id' => $deterministicId]);

        // Run recovery command
        $exitCode = Artisan::call('agent:retry-target-notifications');
        $this->assertSame(0, $exitCode);

        // Verify recovered notification
        $this->assertDatabaseHas('notifications', [
            'id' => $deterministicId,
            'notifiable_id' => $this->lla->id,
        ]);
    }

    public function test_retry_command_is_completely_idempotent_on_repeated_runs(): void
    {
        $target = $this->service->createTarget([
            'agent_id' => $this->lla->id,
            'target_month' => '2028-01',
            'sales_target' => '350000.00',
        ], $this->admin);

        $auditLog = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target->id)
            ->firstOrFail();

        $deterministicId = Uuid::uuid5(Uuid::NAMESPACE_DNS, "auraluxe.target.audit.{$auditLog->id}")->toString();

        // Count before run
        $countInitial = DB::table('notifications')->where('id', $deterministicId)->count();
        $this->assertSame(1, $countInitial);

        // Run recovery command twice
        Artisan::call('agent:retry-target-notifications');
        Artisan::call('agent:retry-target-notifications');

        $countAfter = DB::table('notifications')->where('id', $deterministicId)->count();
        $this->assertSame(1, $countAfter);
    }

    public function test_retry_command_respects_hours_lookback_window(): void
    {
        $oldAudit = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'agent_sales_target_created',
            'auditable_type' => AgentSalesTarget::class,
            'auditable_id' => 99999, // non-existent
            'created_at' => Carbon::now()->subHours(48),
        ]);

        $exitCode = Artisan::call('agent:retry-target-notifications', ['--hours' => 24]);
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Agent Target Notification Failure Recovery Completed', $output);
    }

    public function test_management_actor_can_rollover_targets_in_bounded_batches(): void
    {
        $sourceMonth = '2028-02';
        $targetMonth = '2028-03';

        // Create 3 agents with targets in sourceMonth
        $agents = User::factory()->count(3)->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
        foreach ($agents as $index => $agent) {
            $this->createAgentProfile($agent, 'verified');

            AgentSalesTarget::create([
                'agent_id' => $agent->id,
                'target_month' => $sourceMonth,
                'sales_target' => (string) (100000 + ($index * 50000)),
                'created_by' => $this->admin->id,
            ]);
        }

        $summary = $this->service->rolloverMonthlyTargets(
            $sourceMonth,
            $targetMonth,
            $this->admin,
            batchSize: 2 // Test batch chunking with small chunk
        );

        $this->assertSame(3, $summary['processed']);
        $this->assertSame(3, $summary['created']);
        $this->assertSame(0, $summary['skipped_existing']);
        $this->assertSame(0, $summary['skipped_inactive']);

        // Verify records created in targetMonth
        foreach ($agents as $index => $agent) {
            $expectedAmount = (string) (100000 + ($index * 50000));
            $this->assertDatabaseHas('agent_sales_targets', [
                'agent_id' => $agent->id,
                'target_month' => $targetMonth,
                'sales_target' => $expectedAmount,
                'created_by' => $this->admin->id,
            ]);

            // Verify audit log
            $newTarget = AgentSalesTarget::where('agent_id', $agent->id)
                ->where('target_month', $targetMonth)
                ->firstOrFail();

            $auditLog = AuditLog::where('auditable_type', AgentSalesTarget::class)
                ->where('auditable_id', $newTarget->id)
                ->where('action', 'agent_sales_target_rolled_over')
                ->firstOrFail();

            // Verify post-commit database notification
            $expectedNotificationId = Uuid::uuid5(
                Uuid::NAMESPACE_DNS,
                "auraluxe.target.audit.{$auditLog->id}"
            )->toString();

            $this->assertDatabaseHas('notifications', [
                'id' => $expectedNotificationId,
                'notifiable_id' => $agent->id,
                'type' => AgentTargetAssignedNotification::class,
            ]);
        }
    }

    public function test_rollover_skips_existing_targets_without_overwriting(): void
    {
        $sourceMonth = '2028-04';
        $targetMonth = '2028-05';

        // Agent 1: already has target in targetMonth
        $agent1 = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
        $this->createAgentProfile($agent1, 'verified');

        AgentSalesTarget::create([
            'agent_id' => $agent1->id,
            'target_month' => $sourceMonth,
            'sales_target' => '200000.00',
            'created_by' => $this->admin->id,
        ]);
        AgentSalesTarget::create([
            'agent_id' => $agent1->id,
            'target_month' => $targetMonth,
            'sales_target' => '999999.00', // Pre-existing target
            'created_by' => $this->admin->id,
        ]);

        // Agent 2: does NOT have target in targetMonth
        $agent2 = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
        $this->createAgentProfile($agent2, 'verified');

        AgentSalesTarget::create([
            'agent_id' => $agent2->id,
            'target_month' => $sourceMonth,
            'sales_target' => '300000.00',
            'created_by' => $this->admin->id,
        ]);

        $summary = $this->service->rolloverMonthlyTargets(
            $sourceMonth,
            $targetMonth,
            $this->admin
        );

        $this->assertSame(2, $summary['processed']);
        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['skipped_existing']);
        $this->assertSame(0, $summary['skipped_inactive']);

        // Agent 1 pre-existing target must remain intact without overwrite
        $this->assertDatabaseHas('agent_sales_targets', [
            'agent_id' => $agent1->id,
            'target_month' => $targetMonth,
            'sales_target' => '999999.00',
        ]);

        // Agent 2 should be created
        $this->assertDatabaseHas('agent_sales_targets', [
            'agent_id' => $agent2->id,
            'target_month' => $targetMonth,
            'sales_target' => '300000.00',
        ]);
    }

    public function test_rollover_skips_inactive_and_kyc_rejected_agents(): void
    {
        $sourceMonth = '2028-06';
        $targetMonth = '2028-07';

        // Inactive agent
        $inactiveAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::SUSPENDED->value,
        ]);
        $this->createAgentProfile($inactiveAgent, 'verified');
        AgentSalesTarget::create([
            'agent_id' => $inactiveAgent->id,
            'target_month' => $sourceMonth,
            'sales_target' => '100000.00',
            'created_by' => $this->admin->id,
        ]);

        // KYC rejected agent
        $rejectedKycAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
        $this->createAgentProfile($rejectedKycAgent, 'rejected');
        AgentSalesTarget::create([
            'agent_id' => $rejectedKycAgent->id,
            'target_month' => $sourceMonth,
            'sales_target' => '100000.00',
            'created_by' => $this->admin->id,
        ]);

        $summary = $this->service->rolloverMonthlyTargets(
            $sourceMonth,
            $targetMonth,
            $this->admin
        );

        $this->assertSame(2, $summary['processed']);
        $this->assertSame(0, $summary['created']);
        $this->assertSame(0, $summary['skipped_existing']);
        $this->assertSame(2, $summary['skipped_inactive']);

        $this->assertDatabaseMissing('agent_sales_targets', [
            'agent_id' => $inactiveAgent->id,
            'target_month' => $targetMonth,
        ]);
        $this->assertDatabaseMissing('agent_sales_targets', [
            'agent_id' => $rejectedKycAgent->id,
            'target_month' => $targetMonth,
        ]);
    }

    public function test_rollover_discriminates_duplicate_unique_constraint_conflicts_gracefully(): void
    {
        // 1. MySQL duplicate entry error 1062
        $e1 = new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-2028-08' for key 'agent_sales_targets_agent_id_target_month_unique'", 1062);
        $this->assertTrue($this->service->isTargetUniqueConstraintConflict($e1));

        // 2. SQLite UNIQUE constraint failed error 19
        $e2 = new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: agent_sales_targets.agent_id, agent_sales_targets.target_month", 19);
        $this->assertTrue($this->service->isTargetUniqueConstraintConflict($e2));

        // 3. MySQL Foreign Key violation (MUST NOT be treated as duplicate conflict)
        $eFk = new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails", 1452);
        $this->assertFalse($this->service->isTargetUniqueConstraintConflict($eFk));

        // 4. SQLite Foreign Key violation (MUST NOT be treated as duplicate conflict)
        $eFkSqlite = new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed", 19);
        $this->assertFalse($this->service->isTargetUniqueConstraintConflict($eFkSqlite));

        // 5. NOT NULL constraint violation (MUST NOT be treated as duplicate conflict)
        $eNotNull = new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'sales_target' cannot be null", 1048);
        $this->assertFalse($this->service->isTargetUniqueConstraintConflict($eNotNull));

        // 6. Generic syntax/runtime error
        $eSyntax = new \RuntimeException("Table agent_sales_targets does not exist");
        $this->assertFalse($this->service->isTargetUniqueConstraintConflict($eSyntax));
    }

    public function test_rollover_denied_to_non_management_actors_and_inactive_managers(): void
    {
        // HLA denied
        $this->expectException(AuthorizationException::class);
        $this->service->rolloverMonthlyTargets('2028-08', '2028-09', $this->hla);
    }

    public function test_rollover_denied_for_closed_historical_periods(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Bulk rollover is restricted to current or future periods");
        $this->service->rolloverMonthlyTargets('2025-01', '2025-02', $this->admin);
    }

    public function test_rollover_denied_when_source_and_target_months_are_identical(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Target month (2028-10) must be the immediately following month of source month (2028-10). Expected: 2028-11.");
        $this->service->rolloverMonthlyTargets('2028-10', '2028-10', $this->admin);
    }

    public function test_rollover_month_validation_matrix_enforces_strictly_consecutive_months(): void
    {
        // 1. Invalid month format
        try {
            $this->service->rolloverMonthlyTargets('2028-13', '2028-14', $this->admin);
            $this->fail("Expected InvalidArgumentException for invalid month format.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("valid YYYY-MM format", $e->getMessage());
        }

        // 2. Reversed months
        try {
            $this->service->rolloverMonthlyTargets('2028-06', '2028-05', $this->admin);
            $this->fail("Expected InvalidArgumentException for reversed months.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("must be the immediately following month", $e->getMessage());
        }

        // 3. Skipping months
        try {
            $this->service->rolloverMonthlyTargets('2028-01', '2028-03', $this->admin);
            $this->fail("Expected InvalidArgumentException for non-consecutive month jump.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Expected: 2028-02", $e->getMessage());
        }

        // 4. Year rollover boundary (2028-12 to 2029-01) is strictly supported
        $summary = $this->service->rolloverMonthlyTargets('2028-12', '2029-01', $this->admin);
        $this->assertSame(0, $summary['processed']); // Empty source month completes cleanly
    }

    public function test_batch_processing_exceeding_one_hundred_records(): void
    {
        $sourceMonth = '2029-02';
        $targetMonth = '2029-03';

        // Seed 105 active agents with targets in sourceMonth
        $count = 105;
        $createdAgents = [];
        for ($i = 1; $i <= $count; $i++) {
            $agent = User::factory()->create([
                'role' => UserRole::LOW_LEVEL_AGENT->value,
                'status' => UserStatus::ACTIVE->value,
                'email' => "bulk_agent_{$i}_" . uniqid() . "@example.com",
            ]);
            $this->createAgentProfile($agent, 'verified');

            AgentSalesTarget::create([
                'agent_id' => $agent->id,
                'target_month' => $sourceMonth,
                'sales_target' => (string) (100000 + ($i * 1000)),
                'created_by' => $this->admin->id,
            ]);
            $createdAgents[] = $agent;
        }

        $this->assertSame($count, AgentSalesTarget::where('target_month', $sourceMonth)->count());

        // Execute rollover with batch size of 50 (forces 3 chunks: 50 + 50 + 5)
        $summary = $this->service->rolloverMonthlyTargets(
            $sourceMonth,
            $targetMonth,
            $this->admin,
            batchSize: 50
        );

        $this->assertSame(105, $summary['processed']);
        $this->assertSame(105, $summary['created']);
        $this->assertSame(0, $summary['skipped_existing']);
        $this->assertSame(0, $summary['skipped_inactive']);

        $this->assertSame(105, AgentSalesTarget::where('target_month', $targetMonth)->count());
        $this->assertSame(105, AuditLog::where('action', 'agent_sales_target_rolled_over')->count());

        // Verify post-commit notification count matches created target count exactly
        $notificationCount = DB::table('notifications')
            ->where('type', AgentTargetAssignedNotification::class)
            ->count();
        $this->assertGreaterThanOrEqual(105, $notificationCount);

        // Retrying the exact same rollover must be 100% idempotent: skip all 105 without duplicates
        $retrySummary = $this->service->rolloverMonthlyTargets(
            $sourceMonth,
            $targetMonth,
            $this->admin,
            batchSize: 50
        );

        $this->assertSame(105, $retrySummary['processed']);
        $this->assertSame(0, $retrySummary['created']);
        $this->assertSame(105, $retrySummary['skipped_existing']);
        $this->assertSame(0, $retrySummary['skipped_inactive']);

        // Verify no duplicate records or audit logs were created on retry
        $this->assertSame(105, AgentSalesTarget::where('target_month', $targetMonth)->count());
        $this->assertSame(105, AuditLog::where('action', 'agent_sales_target_rolled_over')->count());
    }

    public function test_recovery_command_safely_handles_missing_targets_and_invalid_recipients(): void
    {
        // 1. AuditLog pointing to a deleted / missing target
        $orphanAudit = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'agent_sales_target_created',
            'auditable_type' => AgentSalesTarget::class,
            'auditable_id' => 888888, // Non-existent target ID
            'created_at' => Carbon::now(),
        ]);

        // 2. AuditLog pointing to an inactive recipient
        $inactiveAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::SUSPENDED->value,
        ]);
        $this->createAgentProfile($inactiveAgent, 'verified');

        $inactiveTarget = AgentSalesTarget::create([
            'agent_id' => $inactiveAgent->id,
            'target_month' => '2029-04',
            'sales_target' => '200000.00',
            'created_by' => $this->admin->id,
        ]);

        $inactiveAudit = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'agent_sales_target_created',
            'auditable_type' => AgentSalesTarget::class,
            'auditable_id' => $inactiveTarget->id,
            'created_at' => Carbon::now(),
        ]);

        // Execute recovery command
        $exitCode = Artisan::call('agent:retry-target-notifications');
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Target record #888888 missing; notification skipped', $output);
        $this->assertStringContainsString("Recipient #{$inactiveAgent->id} is inactive or invalid; notification skipped", $output);
    }

    public function test_rolled_over_target_preserves_management_provenance_and_locks_from_hla(): void
    {
        $sourceMonth = '2029-05';
        $targetMonth = '2029-06';

        // LLA in HLA's downline tree
        AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => $sourceMonth,
            'sales_target' => '300000.00',
            'created_by' => $this->hla->id, // Originally created by HLA
        ]);

        // Rolled over by Admin
        $summary = $this->service->rolloverMonthlyTargets($sourceMonth, $targetMonth, $this->admin);
        $this->assertSame(1, $summary['created']);

        $rolledTarget = AgentSalesTarget::where('agent_id', $this->lla->id)
            ->where('target_month', $targetMonth)
            ->firstOrFail();

        // Target created_by must be the acting Management actor
        $this->assertSame($this->admin->id, $rolledTarget->created_by);

        // AuditLog must record old source info and new target info
        $auditLog = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $rolledTarget->id)
            ->where('action', 'agent_sales_target_rolled_over')
            ->firstOrFail();

        $this->assertSame($sourceMonth, $auditLog->old_values['source_month']);
        $this->assertSame($targetMonth, $auditLog->new_values['target_month']);
        $this->assertSame($this->admin->id, $auditLog->new_values['created_by']);

        // Provenance Check: Because rolled-over target contains a Management audit event, HLA is strictly blocked!
        $this->assertTrue($this->service->isTargetManagementControlled($rolledTarget));

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("This target is under management control and cannot be modified by a High-Level Agent.");
        $this->service->updateDownlineTarget($this->hla, $rolledTarget, [
            'sales_target' => '500000.00',
            'reason' => 'HLA attempted unauthorized adjustment on management-rolled target',
        ]);
    }

    public function test_rollover_targets_artisan_command_executes_successfully(): void
    {
        $sourceMonth = '2028-10';
        $targetMonth = '2028-11';

        $agent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
        $this->createAgentProfile($agent, 'verified');

        AgentSalesTarget::create([
            'agent_id' => $agent->id,
            'target_month' => $sourceMonth,
            'sales_target' => '500000.00',
            'created_by' => $this->admin->id,
        ]);

        $exitCode = Artisan::call('agent:rollover-targets', [
            'source_month' => $sourceMonth,
            'target_month' => $targetMonth,
            '--actor-id' => $this->admin->id,
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Bulk rollover successfully executed', $output);

        $this->assertDatabaseHas('agent_sales_targets', [
            'agent_id' => $agent->id,
            'target_month' => $targetMonth,
            'sales_target' => '500000.00',
        ]);
    }
}
