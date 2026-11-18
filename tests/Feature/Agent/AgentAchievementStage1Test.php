<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentAchievement;
use App\Models\AgentAchievementAward;
use App\Models\AgentAchievementEvaluationRun;
use App\Models\AgentAchievementRequest;
use App\Models\AgentAchievementStreak;
use App\Models\AgentAchievementVersion;
use App\Models\AgentProfile;
use App\Models\User;
use Database\Seeders\AgentAchievementSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgentAchievementStage1Test extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $hla;
    private User $lla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Business Owner
        $this->owner = User::factory()->create([
            'name' => 'Dilshan Ranasinghe',
            'email' => 'owner@auraluxe.lk',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);
        $this->owner->syncRole('owner');

        // 2. Super Admin
        $this->superAdmin = User::factory()->create([
            'name' => 'Naveen Jayawardena',
            'email' => 'superadmin@auraluxe.lk',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);
        $this->superAdmin->syncRole('super_admin');

        // 3. Agent Manager (Staff)
        $this->agentManager = User::factory()->create([
            'name' => 'Kavinda Perera',
            'email' => 'agentmanager@auraluxe.lk',
            'role' => 'agent_manager',
            'status' => UserStatus::ACTIVE,
        ]);
        $this->agentManager->syncRole('agent_manager');

        // 4. HLA
        $this->hla = User::factory()->create([
            'name' => 'High Level Agent',
            'email' => 'hla@auraluxe.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        $this->hla->syncRole('high_level_agent');
        AgentProfile::create([
            'user_id' => $this->hla->id,
            'nic' => '198512345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0.00,
            'sales_tier' => 'bronze',
        ]);

        // 5. LLA
        $this->lla = User::factory()->create([
            'name' => 'Low Level Agent',
            'email' => 'lla@auraluxe.lk',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $this->hla->id,
        ]);
        $this->lla->syncRole('low_level_agent');
        AgentProfile::create([
            'user_id' => $this->lla->id,
            'nic' => '199512345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0.00,
            'sales_tier' => 'bronze',
        ]);
    }

    /**
     * 1. Test database schema exists with all 6 achievement tables and correct columns.
     */
    public function test_all_six_achievement_tables_and_columns_exist(): void
    {
        $tables = [
            'agent_achievements' => [
                'id', 'code', 'name', 'description', 'category', 'badge_tier',
                'badge_icon', 'metric_type', 'threshold_value', 'period_type',
                'is_recurring', 'is_discretionary', 'is_active', 'current_version',
                'sort_order', 'created_by', 'updated_by', 'created_at', 'updated_at',
            ],
            'agent_achievement_versions' => [
                'id', 'achievement_id', 'version', 'name', 'description', 'category',
                'badge_tier', 'metric_type', 'threshold_value', 'period_type',
                'is_recurring', 'is_discretionary', 'qualification_rules_snapshot',
                'change_reason', 'created_by', 'created_at', 'updated_at',
            ],
            'agent_achievement_requests' => [
                'id', 'achievement_id', 'agent_id', 'requested_by', 'status',
                'justification', 'period_key', 'decision_notes', 'decided_by',
                'decided_at', 'created_at', 'updated_at',
            ],
            'agent_achievement_awards' => [
                'id', 'agent_id', 'achievement_id', 'version_id', 'period_key',
                'achieved_value', 'threshold_snapshot', 'status', 'is_discretionary',
                'request_id', 'awarded_at', 'awarded_by', 'revoked_at', 'revoked_by',
                'revocation_reason', 'correction_of_award_id', 'metadata',
                'created_at', 'updated_at',
            ],
            'agent_achievement_streaks' => [
                'id', 'agent_id', 'streak_type', 'current_streak', 'longest_streak',
                'last_qualifying_period', 'streak_started_period', 'is_active',
                'created_at', 'updated_at',
            ],
            'agent_achievement_evaluation_runs' => [
                'id', 'run_type', 'period_type', 'period_key', 'started_at',
                'completed_at', 'status', 'agents_evaluated', 'awards_created',
                'streaks_updated', 'error_summary', 'metadata', 'executed_by',
                'created_at', 'updated_at',
            ],
        ];

        foreach ($tables as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} does not exist.");
            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasColumn($table, $column),
                    "Column {$column} does not exist on {$table}."
                );
            }
        }
    }

    /**
     * 2. Test migration reversibility: down migrations drop all 6 tables cleanly in reverse dependency order.
     */
    public function test_migrations_can_be_rolled_back_cleanly(): void
    {
        $runMigration = require database_path('migrations/2026_11_18_000006_create_agent_achievement_evaluation_runs_table.php');
        $runMigration->down();
        $this->assertFalse(Schema::hasTable('agent_achievement_evaluation_runs'));

        $streakMigration = require database_path('migrations/2026_11_18_000005_create_agent_achievement_streaks_table.php');
        $streakMigration->down();
        $this->assertFalse(Schema::hasTable('agent_achievement_streaks'));

        $awardMigration = require database_path('migrations/2026_11_18_000004_create_agent_achievement_awards_table.php');
        $awardMigration->down();
        $this->assertFalse(Schema::hasTable('agent_achievement_awards'));

        $reqMigration = require database_path('migrations/2026_11_18_000003_create_agent_achievement_requests_table.php');
        $reqMigration->down();
        $this->assertFalse(Schema::hasTable('agent_achievement_requests'));

        $verMigration = require database_path('migrations/2026_11_18_000002_create_agent_achievement_versions_table.php');
        $verMigration->down();
        $this->assertFalse(Schema::hasTable('agent_achievement_versions'));

        $achMigration = require database_path('migrations/2026_11_18_000001_create_agent_achievements_table.php');
        $achMigration->down();
        $this->assertFalse(Schema::hasTable('agent_achievements'));

        // Re-run up() migrations to leave DB intact for subsequent assertions
        $achMigration->up();
        $verMigration->up();
        $reqMigration->up();
        $awardMigration->up();
        $streakMigration->up();
        $runMigration->up();

        $this->assertTrue(Schema::hasTable('agent_achievements'));
        $this->assertTrue(Schema::hasTable('agent_achievement_awards'));
    }

    /**
     * 3. Test uniqueness constraint on agent_achievements.code.
     */
    public function test_achievement_code_uniqueness_is_enforced(): void
    {
        AgentAchievement::create([
            'code' => 'TEST_CODE',
            'name' => 'Test Achievement',
            'description' => 'Test description',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'badge_icon' => 'award',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
        ]);

        $this->expectException(QueryException::class);

        AgentAchievement::create([
            'code' => 'TEST_CODE', // Duplicate code
            'name' => 'Duplicate Achievement',
            'description' => 'Duplicate description',
            'category' => 'personal_sales',
            'badge_tier' => 'silver',
            'badge_icon' => 'award',
            'metric_type' => 'sales_volume',
            'threshold_value' => '100000.00',
            'period_type' => 'monthly',
        ]);
    }

    /**
     * 4. Test uniqueness constraint on agent_achievement_versions (achievement_id, version).
     */
    public function test_achievement_version_uniqueness_is_enforced(): void
    {
        $achievement = AgentAchievement::create([
            'code' => 'VERSION_TEST',
            'name' => 'Version Test',
            'description' => 'Testing versions',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'badge_icon' => 'award',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
        ]);

        AgentAchievementVersion::create([
            'achievement_id' => $achievement->id,
            'version' => 1,
            'name' => 'Version Test v1',
            'description' => 'Testing version 1',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
        ]);

        $this->expectException(QueryException::class);

        // Duplicate version 1 for same achievement
        AgentAchievementVersion::create([
            'achievement_id' => $achievement->id,
            'version' => 1,
            'name' => 'Duplicate Version 1',
            'description' => 'Should fail',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
        ]);
    }

    /**
     * 5. Test uniqueness constraint on agent_achievement_awards (agent_id, achievement_id, period_key).
     */
    public function test_achievement_award_uniqueness_per_period_is_enforced(): void
    {
        $achievement = AgentAchievement::create([
            'code' => 'AWARD_TEST',
            'name' => 'Award Test',
            'description' => 'Testing awards',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'badge_icon' => 'award',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
        ]);

        $version = AgentAchievementVersion::create([
            'achievement_id' => $achievement->id,
            'version' => 1,
            'name' => 'Award Test v1',
            'description' => 'v1',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
        ]);

        AgentAchievementAward::create([
            'agent_id' => $this->lla->id,
            'achievement_id' => $achievement->id,
            'version_id' => $version->id,
            'period_key' => '2026-11',
            'achieved_value' => '52000.00',
            'threshold_snapshot' => '50000.00',
            'status' => 'active',
            'awarded_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Duplicate award for same agent, same achievement, and same period_key
        AgentAchievementAward::create([
            'agent_id' => $this->lla->id,
            'achievement_id' => $achievement->id,
            'version_id' => $version->id,
            'period_key' => '2026-11',
            'achieved_value' => '55000.00',
            'threshold_snapshot' => '50000.00',
            'status' => 'active',
            'awarded_at' => now(),
        ]);
    }

    /**
     * 6. Test uniqueness constraint on agent_achievement_streaks (agent_id, streak_type).
     */
    public function test_streak_uniqueness_per_type_is_enforced(): void
    {
        AgentAchievementStreak::create([
            'agent_id' => $this->lla->id,
            'streak_type' => 'target_achievement',
            'current_streak' => 3,
            'longest_streak' => 3,
            'last_qualifying_period' => '2026-10',
            'streak_started_period' => '2026-08',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        // Duplicate streak for same agent and streak_type
        AgentAchievementStreak::create([
            'agent_id' => $this->lla->id,
            'streak_type' => 'target_achievement',
            'current_streak' => 1,
            'longest_streak' => 1,
            'last_qualifying_period' => '2026-10',
            'streak_started_period' => '2026-10',
            'is_active' => true,
        ]);
    }

    /**
     * 7. Test User deletion protection: users with achievement awards cannot be deleted.
     */
    public function test_user_with_achievement_awards_cannot_be_deleted(): void
    {
        $achievement = AgentAchievement::create([
            'code' => 'PROTECT_TEST',
            'name' => 'Protect Test',
            'description' => 'Test',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'badge_icon' => 'award',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
        ]);

        $version = AgentAchievementVersion::create([
            'achievement_id' => $achievement->id,
            'version' => 1,
            'name' => 'v1',
            'description' => 'v1',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
        ]);

        AgentAchievementAward::create([
            'agent_id' => $this->lla->id,
            'achievement_id' => $achievement->id,
            'version_id' => $version->id,
            'period_key' => '2026-11',
            'achieved_value' => '50000.00',
            'threshold_snapshot' => '50000.00',
            'status' => 'active',
            'awarded_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Integrity Violation: User '{$this->lla->name}' ({$this->lla->email}) has associated achievement awards and cannot be deleted.");

        $this->lla->delete();
    }

    /**
     * 8. Test staff FK nullOnDelete: deleting a creator or awarder sets FK to null without deleting the record.
     */
    public function test_staff_deletion_nulls_created_by_and_awarded_by(): void
    {
        $staff = User::factory()->create([
            'role' => 'agent_manager',
            'status' => UserStatus::ACTIVE,
        ]);

        $achievement = AgentAchievement::create([
            'code' => 'STAFF_FK_TEST',
            'name' => 'Staff FK Test',
            'description' => 'Testing nullOnDelete',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'badge_icon' => 'award',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
            'created_by' => $staff->id,
        ]);

        $version = AgentAchievementVersion::create([
            'achievement_id' => $achievement->id,
            'version' => 1,
            'name' => 'v1',
            'description' => 'v1',
            'category' => 'personal_sales',
            'badge_tier' => 'bronze',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
            'created_by' => $staff->id,
        ]);

        $award = AgentAchievementAward::create([
            'agent_id' => $this->lla->id,
            'achievement_id' => $achievement->id,
            'version_id' => $version->id,
            'period_key' => '2026-11',
            'achieved_value' => '50000.00',
            'threshold_snapshot' => '50000.00',
            'status' => 'active',
            'awarded_at' => now(),
            'awarded_by' => $staff->id,
        ]);

        $staff->delete();

        $achievement->refresh();
        $version->refresh();
        $award->refresh();

        $this->assertNull($achievement->created_by);
        $this->assertNull($version->created_by);
        $this->assertNull($award->awarded_by);
    }

    /**
     * 9. Test Eloquent model relationships, casts, and data mapping.
     */
    public function test_all_model_relationships_and_casts_work_properly(): void
    {
        $achievement = AgentAchievement::create([
            'code' => 'REL_TEST',
            'name' => 'Relationship Test',
            'description' => 'Deep test of relationships',
            'category' => 'leadership',
            'badge_tier' => 'gold',
            'badge_icon' => 'crown',
            'metric_type' => 'sales_volume',
            'threshold_value' => '300000.00',
            'period_type' => 'monthly',
            'is_recurring' => true,
            'is_discretionary' => false,
            'is_active' => true,
            'current_version' => 1,
            'sort_order' => 5,
            'created_by' => $this->owner->id,
            'updated_by' => $this->superAdmin->id,
        ]);

        $this->assertTrue($achievement->is_recurring);
        $this->assertFalse($achievement->is_discretionary);
        $this->assertTrue($achievement->is_active);
        $this->assertEquals('300000.00', $achievement->threshold_value);
        $this->assertEquals(1, $achievement->current_version);
        $this->assertEquals($this->owner->id, $achievement->creator->id);
        $this->assertEquals($this->superAdmin->id, $achievement->updater->id);

        $version = AgentAchievementVersion::create([
            'achievement_id' => $achievement->id,
            'version' => 1,
            'name' => 'Relationship Test v1',
            'description' => 'Version 1',
            'category' => 'leadership',
            'badge_tier' => 'gold',
            'metric_type' => 'sales_volume',
            'threshold_value' => '300000.00',
            'period_type' => 'monthly',
            'is_recurring' => true,
            'is_discretionary' => false,
            'qualification_rules_snapshot' => ['min_sales' => 300000],
            'change_reason' => 'Genesis',
            'created_by' => $this->owner->id,
        ]);

        $this->assertIsArray($version->qualification_rules_snapshot);
        $this->assertEquals(300000, $version->qualification_rules_snapshot['min_sales']);
        $this->assertEquals($achievement->id, $version->achievement->id);
        $this->assertCount(1, $achievement->versions);

        $request = AgentAchievementRequest::create([
            'achievement_id' => $achievement->id,
            'agent_id' => $this->lla->id,
            'requested_by' => $this->agentManager->id,
            'status' => 'approved',
            'justification' => 'Outstanding leadership demonstrated',
            'period_key' => '2026-11',
            'decision_notes' => 'Approved by Owner',
            'decided_by' => $this->owner->id,
            'decided_at' => now(),
        ]);

        $this->assertEquals($this->lla->id, $request->agent->id);
        $this->assertEquals($this->agentManager->id, $request->requester->id);
        $this->assertEquals($this->owner->id, $request->decider->id);
        $this->assertEquals($achievement->id, $request->achievement->id);

        $originalAward = AgentAchievementAward::create([
            'agent_id' => $this->lla->id,
            'achievement_id' => $achievement->id,
            'version_id' => $version->id,
            'period_key' => '2026-10',
            'achieved_value' => '295000.00',
            'threshold_snapshot' => '300000.00',
            'status' => 'corrected',
            'is_discretionary' => false,
            'awarded_at' => now()->subMonth(),
            'awarded_by' => $this->owner->id,
            'metadata' => ['note' => 'Original incorrect calculation'],
        ]);

        $correctedAward = AgentAchievementAward::create([
            'agent_id' => $this->lla->id,
            'achievement_id' => $achievement->id,
            'version_id' => $version->id,
            'period_key' => '2026-11',
            'achieved_value' => '305000.00',
            'threshold_snapshot' => '300000.00',
            'status' => 'active',
            'is_discretionary' => false,
            'request_id' => $request->id,
            'awarded_at' => now(),
            'awarded_by' => $this->owner->id,
            'correction_of_award_id' => $originalAward->id,
            'metadata' => ['corrected_from_period' => '2026-10'],
        ]);

        $this->assertEquals($originalAward->id, $correctedAward->correctionOf->id);
        $this->assertCount(1, $originalAward->corrections);
        $this->assertEquals($correctedAward->id, $request->award->id);
        $this->assertEquals($request->id, $correctedAward->request->id);
        $this->assertIsArray($correctedAward->metadata);
        $this->assertEquals('2026-10', $correctedAward->metadata['corrected_from_period']);

        // User relationships
        $this->assertCount(2, $this->lla->achievementAwards);
        $this->assertCount(1, $this->lla->achievementRequests);

        // Streaks
        $streak = AgentAchievementStreak::create([
            'agent_id' => $this->lla->id,
            'streak_type' => 'target_achievement',
            'current_streak' => 4,
            'longest_streak' => 6,
            'last_qualifying_period' => '2026-11',
            'streak_started_period' => '2026-08',
            'is_active' => true,
        ]);

        $this->assertEquals($this->lla->id, $streak->agent->id);
        $this->assertCount(1, $this->lla->achievementStreaks);

        // Evaluation Runs
        $run = AgentAchievementEvaluationRun::create([
            'run_type' => 'automatic_cron',
            'period_type' => 'monthly',
            'period_key' => '2026-11',
            'started_at' => now(),
            'completed_at' => now()->addMinutes(2),
            'status' => 'completed',
            'agents_evaluated' => 50,
            'awards_created' => 12,
            'streaks_updated' => 45,
            'executed_by' => $this->superAdmin->id,
        ]);

        $this->assertEquals($this->superAdmin->id, $run->executor->id);
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(50, $run->agents_evaluated);
    }

    /**
     * 10. Test RBAC permissions: Owner, Super Admin, Agent Manager, and Agents.
     */
    public function test_achievement_rbac_permissions(): void
    {
        // 1. Business Owner has all permissions (including financial restricted publish/award/revoke)
        $this->assertTrue($this->owner->hasPermissionTo('agents.achievements.view'));
        $this->assertTrue($this->owner->hasPermissionTo('agents.achievements.manage'));
        $this->assertTrue($this->owner->hasPermissionTo('agents.achievements.publish'));
        $this->assertTrue($this->owner->hasPermissionTo('agents.achievements.request'));
        $this->assertTrue($this->owner->hasPermissionTo('agents.achievements.award'));
        $this->assertTrue($this->owner->hasPermissionTo('agents.achievements.revoke'));
        $this->assertTrue($this->owner->hasPermissionTo('agents.achievements.export'));

        // 2. Super Admin has view, technical manage, and export ONLY
        $this->assertTrue($this->superAdmin->hasPermissionTo('agents.achievements.view'));
        $this->assertTrue($this->superAdmin->hasPermissionTo('agents.achievements.manage'));
        $this->assertTrue($this->superAdmin->hasPermissionTo('agents.achievements.export'));
        // Super Admin strictly does NOT have publish (business authority) or request (discretionary nomination)
        $this->assertFalse($this->superAdmin->hasPermissionTo('agents.achievements.publish'));
        $this->assertFalse($this->superAdmin->hasPermissionTo('agents.achievements.request'));
        $this->assertFalse($this->superAdmin->hasPermissionTo('agents.achievements.award'));
        $this->assertFalse($this->superAdmin->hasPermissionTo('agents.achievements.revoke'));

        // 3. Agent Manager has view, request (nominations), export, but NOT technical manage, publish, award, or revoke
        $this->assertTrue($this->agentManager->hasPermissionTo('agents.achievements.view'));
        $this->assertTrue($this->agentManager->hasPermissionTo('agents.achievements.request'));
        $this->assertTrue($this->agentManager->hasPermissionTo('agents.achievements.export'));
        $this->assertFalse($this->agentManager->hasPermissionTo('agents.achievements.manage'));
        $this->assertFalse($this->agentManager->hasPermissionTo('agents.achievements.publish'));
        $this->assertFalse($this->agentManager->hasPermissionTo('agents.achievements.award'));
        $this->assertFalse($this->agentManager->hasPermissionTo('agents.achievements.revoke'));

        // 4. Agents (HLA and LLA) have no administrative achievement permissions
        $this->assertFalse($this->hla->hasPermissionTo('agents.achievements.view'));
        $this->assertFalse($this->hla->hasPermissionTo('agents.achievements.manage'));
        $this->assertFalse($this->hla->hasPermissionTo('agents.achievements.publish'));
        $this->assertFalse($this->hla->hasPermissionTo('agents.achievements.request'));
        $this->assertFalse($this->hla->hasPermissionTo('agents.achievements.award'));
        $this->assertFalse($this->hla->hasPermissionTo('agents.achievements.revoke'));
        $this->assertFalse($this->hla->hasPermissionTo('agents.achievements.export'));

        $this->assertFalse($this->lla->hasPermissionTo('agents.achievements.view'));
        $this->assertFalse($this->lla->hasPermissionTo('agents.achievements.manage'));
        $this->assertFalse($this->lla->hasPermissionTo('agents.achievements.publish'));
        $this->assertFalse($this->lla->hasPermissionTo('agents.achievements.request'));
        $this->assertFalse($this->lla->hasPermissionTo('agents.achievements.award'));
        $this->assertFalse($this->lla->hasPermissionTo('agents.achievements.revoke'));
        $this->assertFalse($this->lla->hasPermissionTo('agents.achievements.export'));
    }

    /**
     * 11. Test policy gates prove Super Admin cannot publish definitions or submit discretionary nominations.
     */
    public function test_super_admin_cannot_publish_definitions_or_submit_discretionary_nominations(): void
    {
        $achievement = AgentAchievement::create([
            'code' => 'GATE_TEST',
            'name' => 'Gate Test',
            'description' => 'Testing gate checks',
            'category' => 'sales_milestone',
            'badge_tier' => 'bronze',
            'badge_icon' => 'award',
            'metric_type' => 'sales_volume',
            'threshold_value' => '50000.00',
            'period_type' => 'monthly',
        ]);

        // Super Admin checks: Technical management allowed, but business publishing and nominations strictly denied
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('manageTechnical', $achievement));
        $this->assertFalse(Gate::forUser($this->superAdmin)->allows('publish', $achievement));
        $this->assertFalse(Gate::forUser($this->superAdmin)->allows('request', $achievement));
        $this->assertFalse(Gate::forUser($this->superAdmin)->allows('award', $achievement));
        $this->assertFalse(Gate::forUser($this->superAdmin)->allows('revoke', $achievement));

        // Agent Manager checks: Discretionary nominations allowed, but publishing, technical management, and awarding denied
        $this->assertTrue(Gate::forUser($this->agentManager)->allows('request', $achievement));
        $this->assertFalse(Gate::forUser($this->agentManager)->allows('manageTechnical', $achievement));
        $this->assertFalse(Gate::forUser($this->agentManager)->allows('publish', $achievement));
        $this->assertFalse(Gate::forUser($this->agentManager)->allows('award', $achievement));
        $this->assertFalse(Gate::forUser($this->agentManager)->allows('revoke', $achievement));

        // Business Owner checks: Full authority over publishing, awarding, revoking, requesting, and technical management
        $this->assertTrue(Gate::forUser($this->owner)->allows('manageTechnical', $achievement));
        $this->assertTrue(Gate::forUser($this->owner)->allows('publish', $achievement));
        $this->assertTrue(Gate::forUser($this->owner)->allows('request', $achievement));
        $this->assertTrue(Gate::forUser($this->owner)->allows('award', $achievement));
        $this->assertTrue(Gate::forUser($this->owner)->allows('revoke', $achievement));
    }

    /**
     * 12. Test AgentAchievementSeeder seeds all 12 approved catalog definitions and versions idempotently.
     */
    public function test_agent_achievement_seeder_seeds_all_twelve_catalog_definitions_and_versions(): void
    {
        $this->seed(AgentAchievementSeeder::class);

        $this->assertEquals(12, AgentAchievement::count());
        $this->assertEquals(12, AgentAchievementVersion::count());

        $expectedCatalog = [
            'SALES_FIRST_ORDER' => ['name' => 'Rising Star', 'threshold' => '1.00', 'metric' => 'order_count', 'cat' => 'sales_milestone', 'tier' => 'bronze', 'recurring' => false, 'disc' => false],
            'SALES_100K_CUMULATIVE' => ['name' => 'Century Club', 'threshold' => '100000.00', 'metric' => 'sales_volume', 'cat' => 'sales_milestone', 'tier' => 'silver', 'recurring' => false, 'disc' => false],
            'SALES_500K_CUMULATIVE' => ['name' => 'High Flyer', 'threshold' => '500000.00', 'metric' => 'sales_volume', 'cat' => 'sales_milestone', 'tier' => 'gold', 'recurring' => false, 'disc' => false],
            'SALES_1M_CUMULATIVE' => ['name' => 'Emerald Ambassador', 'threshold' => '1000000.00', 'metric' => 'sales_volume', 'cat' => 'sales_milestone', 'tier' => 'platinum', 'recurring' => false, 'disc' => false],
            'TARGET_MET_MONTHLY' => ['name' => 'Target Crusher', 'threshold' => '100.00', 'metric' => 'target_attainment_pct', 'cat' => 'target_attainment', 'tier' => 'silver', 'recurring' => true, 'disc' => false],
            'TARGET_OVERACHIEVE_125' => ['name' => 'Overachiever', 'threshold' => '125.00', 'metric' => 'target_attainment_pct', 'cat' => 'target_attainment', 'tier' => 'gold', 'recurring' => true, 'disc' => false],
            'TARGET_STREAK_3M' => ['name' => 'Consistency Master', 'threshold' => '3.00', 'metric' => 'streak_months', 'cat' => 'consistency_streak', 'tier' => 'gold', 'recurring' => true, 'disc' => false],
            'CUSTOMERS_ACQUIRED_10' => ['name' => 'Client Magnet', 'threshold' => '10.00', 'metric' => 'customer_count', 'cat' => 'customer_growth', 'tier' => 'bronze', 'recurring' => false, 'disc' => false],
            'TEAM_BUILDER_DIRECT_5' => ['name' => 'Team Builder', 'threshold' => '5.00', 'metric' => 'active_recruits', 'cat' => 'team_building', 'tier' => 'silver', 'recurring' => false, 'disc' => false],
            'TEAM_SALES_1M_MONTHLY' => ['name' => 'Dynasty Captain', 'threshold' => '1000000.00', 'metric' => 'downline_volume', 'cat' => 'team_building', 'tier' => 'platinum', 'recurring' => true, 'disc' => false],
            'LEADERBOARD_MONTH_TOP1' => ['name' => 'Ceylon Number One', 'threshold' => '1.00', 'metric' => 'leaderboard_rank', 'cat' => 'special_honor', 'tier' => 'diamond', 'recurring' => true, 'disc' => false],
            'HONOR_CONVENTION_AWARD' => ['name' => "Founder's Choice", 'threshold' => '0.00', 'metric' => 'manual', 'cat' => 'special_honor', 'tier' => 'diamond', 'recurring' => false, 'disc' => true],
        ];

        foreach ($expectedCatalog as $code => $expected) {
            $achievement = AgentAchievement::where('code', $code)->first();
            $this->assertNotNull($achievement, "Achievement {$code} was not seeded.");
            $this->assertEquals($expected['name'], $achievement->name);
            $this->assertEquals($expected['threshold'], $achievement->threshold_value);
            $this->assertEquals($expected['metric'], $achievement->metric_type);
            $this->assertEquals($expected['cat'], $achievement->category);
            $this->assertEquals($expected['tier'], $achievement->badge_tier);
            $this->assertEquals($expected['recurring'], $achievement->is_recurring);
            $this->assertEquals($expected['disc'], $achievement->is_discretionary);
            $this->assertTrue($achievement->is_active);
            $this->assertEquals(1, $achievement->current_version);

            $version = AgentAchievementVersion::where('achievement_id', $achievement->id)
                ->where('version', 1)
                ->first();
            $this->assertNotNull($version, "Version 1 for achievement {$code} was not seeded.");
            $this->assertEquals($achievement->name, $version->name);
            $this->assertEquals($achievement->category, $version->category);
            $this->assertEquals($achievement->threshold_value, $version->threshold_value);
            $this->assertEquals($achievement->metric_type, $version->metric_type);
            $this->assertIsArray($version->qualification_rules_snapshot);
        }

        // Idempotency: re-running seeder does not create duplicates
        $this->seed(AgentAchievementSeeder::class);
        $this->assertEquals(12, AgentAchievement::count());
        $this->assertEquals(12, AgentAchievementVersion::count());
    }
}

