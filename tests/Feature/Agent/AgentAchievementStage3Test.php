<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentAchievement;
use App\Models\AgentAchievementAward;
use App\Models\AgentAchievementStreak;
use App\Models\AgentProfile;
use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AgentAchievementAwardedNotification;
use App\Services\AgentAchievementService;
use App\Services\AgentAnalyticsService;
use Carbon\Carbon;
use Database\Seeders\AgentAchievementSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AgentAchievementStage3Test extends TestCase
{
    use RefreshDatabase;

    private AgentAchievementService $achievementService;
    private User $owner;
    private User $superAdmin;
    private User $hla;
    private User $lla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AgentAchievementSeeder::class);

        $this->achievementService = app(AgentAchievementService::class);

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

        // 3. HLA
        $this->hla = User::factory()->create([
            'name' => 'Dilini Senanayake (HLA)',
            'email' => 'hla@auraluxe.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'HLAMAIN01',
        ]);
        $this->hla->syncRole('high_level_agent');
        AgentProfile::create([
            'user_id' => $this->hla->id,
            'nic' => '198812345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0.00,
            'sales_tier' => 'bronze',
        ]);

        // 4. LLA
        $this->lla = User::factory()->create([
            'name' => 'Saman Kumara (LLA)',
            'email' => 'lla@auraluxe.lk',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $this->hla->id,
            'referral_code' => 'LLAMAIN01',
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

    private function setTarget(User $agent, string $month, string $targetAmount): AgentSalesTarget
    {
        return AgentSalesTarget::create([
            'agent_id' => $agent->id,
            'target_month' => $month,
            'sales_target' => $targetAmount,
            'created_by' => $this->owner->id,
        ]);
    }

    private function createOrderForAgentInMonth(
        User $agent,
        string $month,
        string|float $subtotal,
        string $status = 'delivered',
        string|float $discount = '0.00',
        ?string $orderNumber = null
    ): Order {
        static $orderSeq = 1;
        $orderNumber = $orderNumber ?: 'ORD-STAGE3-' . str_pad((string) $orderSeq++, 4, '0', STR_PAD_LEFT);
        $subtotalStr = AgentAnalyticsService::normalizeDecimalString($subtotal, false);
        $discountStr = AgentAnalyticsService::normalizeDecimalString($discount, true);
        $total = bcadd(bcsub($subtotalStr, $discountStr, 2), '350.00', 2);

        $placedAt = Carbon::createFromFormat('Y-m', $month, 'Asia/Colombo')->startOfMonth()->addDays(5)->setTime(10, 0, 0);

        return Order::create([
            'order_number' => $orderNumber,
            'agent_id' => $agent->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+94771234567',
            'shipping_address' => 'Colombo 07',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => $subtotalStr,
            'discount_amount' => $discountStr,
            'delivery_charge' => '350.00',
            'total_amount' => $total,
            'payment_method' => 'cod',
            'status' => $status,
            'tracking_token' => 'TOK-' . $orderNumber,
            'placed_at' => $placedAt,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
        ]);
    }

    /**
     * 1. Test 100% target attainment qualifies for TARGET_MET_MONTHLY (Silver).
     */
    public function test_monthly_target_100_percent_attainment_qualifies_for_target_crusher(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');

        $result = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $this->assertTrue($result['eligible']);
        $this->assertTrue($result['has_target']);
        $this->assertEquals('100.00', $result['attainment_pct']);
        $this->assertEquals(1, $result['awarded']);

        $award = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->whereHas('achievement', fn($q) => $q->where('code', AgentAchievementService::CODE_TARGET_MET))
            ->first();

        $this->assertNotNull($award);
        $this->assertEquals('active', $award->status);
        $this->assertEquals('100.00', $award->threshold_snapshot);
        $this->assertEquals('100.00', $award->achieved_value);

        // Verify TARGET_OVERACHIEVE_125 was not awarded
        $overachieveAward = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->whereHas('achievement', fn($q) => $q->where('code', AgentAchievementService::CODE_TARGET_OVERACHIEVE_125))
            ->first();
        $this->assertNull($overachieveAward);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_achievement_awarded',
            'auditable_type' => AgentAchievementAward::class,
            'auditable_id' => $award->id,
        ]);
    }

    /**
     * 2. Test attainment strictly below 100% fails to qualify.
     */
    public function test_monthly_target_below_100_percent_fails_to_qualify(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '99999.00', 'delivered');

        $result = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $this->assertTrue($result['eligible']);
        $this->assertTrue($result['has_target']);
        $this->assertEquals('99.99', $result['attainment_pct']);
        $this->assertEquals(0, $result['awarded']);

        $awardCount = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->count();
        $this->assertEquals(0, $awardCount);
    }

    /**
     * 3. Test 125% target attainment qualifies for BOTH TARGET_MET_MONTHLY and TARGET_OVERACHIEVE_125.
     */
    public function test_monthly_target_125_percent_attainment_qualifies_both_achievements(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '125000.00', 'delivered');

        $result = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $this->assertTrue($result['eligible']);
        $this->assertEquals('125.00', $result['attainment_pct']);
        $this->assertEquals(2, $result['awarded']);

        $metAward = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->whereHas('achievement', fn($q) => $q->where('code', AgentAchievementService::CODE_TARGET_MET))
            ->first();
        $this->assertNotNull($metAward);
        $this->assertEquals('active', $metAward->status);

        $overachieveAward = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->whereHas('achievement', fn($q) => $q->where('code', AgentAchievementService::CODE_TARGET_OVERACHIEVE_125))
            ->first();
        $this->assertNotNull($overachieveAward);
        $this->assertEquals('active', $overachieveAward->status);
    }

    /**
     * 4. Test missing or zero target rejects qualification and breaks streak.
     */
    public function test_missing_or_non_positive_target_rejects_qualification(): void
    {
        $month = '2026-10';
        // Sales exist, but NO target is assigned
        $this->createOrderForAgentInMonth($this->lla, $month, '150000.00', 'delivered');

        $result = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $this->assertTrue($result['eligible']);
        $this->assertFalse($result['has_target']);
        $this->assertNull($result['attainment_pct']);
        $this->assertEquals(0, $result['awarded']);

        $awardCount = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->count();
        $this->assertEquals(0, $awardCount);
    }

    /**
     * 5. Test ineligible agents (e.g. unverified KYC or suspended) are rejected.
     */
    public function test_ineligible_agents_are_rejected_from_monthly_evaluations(): void
    {
        $this->lla->status = UserStatus::SUSPENDED;
        $this->lla->save();

        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');

        $result = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $this->assertFalse($result['eligible']);
        $this->assertEquals(0, $result['awarded']);
    }

    /**
     * 6. Test duplicate monthly evaluations are completely idempotent.
     */
    public function test_monthly_evaluation_is_fully_idempotent(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');

        // First run
        $result1 = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);
        $this->assertEquals(1, $result1['awarded']);

        // Second run
        $result2 = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);
        $this->assertEquals(0, $result2['awarded']);
        $this->assertEquals(0, $result2['corrected']);
        $this->assertEquals(0, $result2['requalified']);

        $totalAwards = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->count();
        $this->assertEquals(1, $totalAwards);
    }

    /**
     * 7. Test refund in month transitions award to corrected.
     */
    public function test_refund_in_same_month_adjusts_award_to_corrected(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $order = $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');

        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $award = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->first();
        $this->assertEquals('active', $award->status);

        // Customer returns order
        $order->status = 'returned';
        $order->save();

        $result = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);
        $this->assertEquals(1, $result['corrected']);

        $award->refresh();
        $this->assertEquals('corrected', $award->status);
        $this->assertNotNull($award->revocation_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_achievement_award_corrected',
            'auditable_type' => AgentAchievementAward::class,
            'auditable_id' => $award->id,
        ]);
    }

    /**
     * 8. Test subsequent order in same month restores corrected award to active.
     */
    public function test_subsequent_order_in_same_month_restores_corrected_award(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $order = $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        // Refund order
        $order->status = 'returned';
        $order->save();
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        // New sales arrive
        $this->createOrderForAgentInMonth($this->lla, $month, '110000.00', 'delivered');
        $result = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $this->assertEquals(1, $result['requalified']);

        $award = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->first();
        $this->assertEquals('active', $award->status);
        $this->assertNull($award->revocation_reason);
    }

    /**
     * 9. Test revoked monthly award is permanently protected from automated reinstatement.
     */
    public function test_revoked_monthly_award_never_reinstated_automatically(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $award = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->first();

        // Business Owner revokes the award
        $award->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $this->owner->id,
            'revocation_reason' => 'Compliance policy violation.',
        ]);

        // Evaluate again with qualifying sales for TARGET_MET_MONTHLY (110% attainment, < 125%)
        $this->createOrderForAgentInMonth($this->lla, $month, '10000.00', 'delivered');
        $result = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $this->assertEquals(0, $result['awarded']);
        $this->assertEquals(0, $result['requalified']);

        $award->refresh();
        $this->assertEquals('revoked', $award->status);
    }

    /**
     * 10. Test 3-month consecutive streak qualifies for TARGET_STREAK_3M (Consistency Master).
     */
    public function test_three_month_consecutive_streak_qualifies_consistency_master(): void
    {
        $months = ['2026-08', '2026-09', '2026-10'];

        foreach ($months as $idx => $m) {
            $this->setTarget($this->lla, $m, '50000.00');
            $this->createOrderForAgentInMonth($this->lla, $m, '60000.00', 'delivered');
            $res = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $m);

            $expectedStreak = $idx + 1;
            $this->assertEquals($expectedStreak, $res['streak_months']);
        }

        $streak = AgentAchievementStreak::where('agent_id', $this->lla->id)
            ->where('streak_type', 'target_achievement')
            ->first();

        $this->assertNotNull($streak);
        $this->assertEquals(3, $streak->current_streak);
        $this->assertEquals(3, $streak->longest_streak);
        $this->assertEquals('2026-10', $streak->last_qualifying_period);

        // Verify TARGET_STREAK_3M award was created for 2026-10
        $streakAward = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', '2026-10')
            ->whereHas('achievement', fn($q) => $q->where('code', AgentAchievementService::CODE_TARGET_STREAK_3M))
            ->first();

        $this->assertNotNull($streakAward);
        $this->assertEquals('active', $streakAward->status);
        $this->assertEquals('3.00', $streakAward->threshold_snapshot);
        $this->assertEquals('3.00', $streakAward->achieved_value);
    }

    /**
     * 11. Test missing or gap month breaks the consecutive streak.
     */
    public function test_missed_or_gap_month_breaks_consecutive_streak(): void
    {
        // Month 1 (2026-07): Target Met -> streak 1
        $this->setTarget($this->lla, '2026-07', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-07', '50000.00', 'delivered');
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-07');

        // Month 2 (2026-08): Target NOT Met -> streak breaks to 0
        $this->setTarget($this->lla, '2026-08', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-08', '20000.00', 'delivered');
        $res2 = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-08');
        $this->assertEquals(0, $res2['streak_months']);

        $streak = AgentAchievementStreak::where('agent_id', $this->lla->id)
            ->where('streak_type', 'target_achievement')
            ->first();
        $this->assertEquals(0, $streak->current_streak);
        $this->assertEquals(1, $streak->longest_streak); // Longest streak preserved

        // Month 3 (2026-09): Target Met -> starts new streak at 1
        $this->setTarget($this->lla, '2026-09', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-09', '50000.00', 'delivered');
        $res3 = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-09');
        $this->assertEquals(1, $res3['streak_months']);

        $streak->refresh();
        $this->assertEquals(1, $streak->current_streak);
        $this->assertEquals('2026-09', $streak->streak_started_period);
    }

    /**
     * 12. Test streak duplicate prevention for the same 3-month cycle.
     */
    public function test_streak_duplicate_prevention_on_same_cycle(): void
    {
        $months = ['2026-08', '2026-09', '2026-10'];
        foreach ($months as $m) {
            $this->setTarget($this->lla, $m, '50000.00');
            $this->createOrderForAgentInMonth($this->lla, $m, '50000.00', 'delivered');
            $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $m);
        }

        // Re-evaluate 2026-10
        $resRe = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-10');
        $this->assertEquals(0, $resRe['awarded']);

        // Month 4: streak 4 (not a multiple of 3)
        $this->setTarget($this->lla, '2026-11', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-11', '50000.00', 'delivered');
        $res4 = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-11');
        $this->assertEquals(4, $res4['streak_months']);

        $streakAwardsCount = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->whereHas('achievement', fn($q) => $q->where('code', AgentAchievementService::CODE_TARGET_STREAK_3M))
            ->count();
        $this->assertEquals(1, $streakAwardsCount);
    }

    /**
     * 13. Test notification dispatch via database with deterministic UUIDv5 primary key.
     */
    public function test_notification_dispatched_via_database_with_deterministic_uuidv5(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');

        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $award = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->first();
        $this->assertNotNull($award);

        $expectedNotificationId = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.achievement.award.{$award->id}.monthly_target"
        )->toString();

        $notificationRow = DB::table('notifications')->where('id', $expectedNotificationId)->first();
        $this->assertNotNull($notificationRow, 'Database notification was not created with deterministic UUIDv5.');

        $this->assertEquals(AgentAchievementAwardedNotification::class, $notificationRow->type);
        $this->assertEquals(User::class, $notificationRow->notifiable_type);
        $this->assertEquals($this->lla->id, $notificationRow->notifiable_id);

        $payload = json_decode($notificationRow->data, true);
        $this->assertStringContainsString('Target Crusher', $payload['title']);
        $this->assertEquals('silver', $payload['badge_tier']);
        $this->assertEquals($award->id, $payload['award_id']);
        $this->assertEquals('/agent/achievements', $payload['action_url']);
    }

    /**
     * 14. Test deterministic notification idempotency prevents duplicate notifications.
     */
    public function test_deterministic_notification_idempotency_prevents_duplicate_notifications(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');

        // Evaluate twice
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $notifications = DB::table('notifications')
            ->where('notifiable_id', $this->lla->id)
            ->where('type', AgentAchievementAwardedNotification::class)
            ->get();

        $this->assertCount(1, $notifications);
    }

    /**
     * 15. Test notification failure does not corrupt or roll back award transaction.
     */
    public function test_notification_failure_does_not_corrupt_or_rollback_award_transaction(): void
    {
        $award = AgentAchievementAward::create([
            'agent_id' => $this->lla->id,
            'achievement_id' => AgentAchievement::where('code', AgentAchievementService::CODE_FIRST_ORDER)->first()->id,
            'version_id' => 1,
            'period_key' => 'lifetime',
            'achieved_value' => '1.00',
            'threshold_snapshot' => '1.00',
            'status' => 'active',
            'awarded_at' => now(),
        ]);

        // Manually dispatch award notification to invalid agent or test exception handling
        $this->achievementService->dispatchAwardNotification($award, 'earned');

        // Verify award remains committed and active
        $award->refresh();
        $this->assertEquals('active', $award->status);
    }

    /**
     * 16. Test zero notifications are dispatched for revoked awards.
     */
    public function test_zero_notifications_dispatched_for_revoked_awards(): void
    {
        $award = AgentAchievementAward::create([
            'agent_id' => $this->lla->id,
            'achievement_id' => AgentAchievement::where('code', AgentAchievementService::CODE_FIRST_ORDER)->first()->id,
            'version_id' => 1,
            'period_key' => 'lifetime',
            'achieved_value' => '1.00',
            'threshold_snapshot' => '1.00',
            'status' => 'revoked',
            'awarded_at' => now(),
            'revoked_at' => now(),
            'revocation_reason' => 'Test revocation',
        ]);

        $this->achievementService->dispatchAwardNotification($award, 'earned');

        $notificationCount = DB::table('notifications')
            ->where('notifiable_id', $this->lla->id)
            ->count();

        $this->assertEquals(0, $notificationCount);
    }

    /**
     * 17. Test monthly batch evaluation and Artisan command.
     */
    public function test_monthly_batch_evaluation_and_artisan_command(): void
    {
        $month = '2026-10';
        $this->setTarget($this->hla, $month, '200000.00');
        $this->createOrderForAgentInMonth($this->hla, $month, '250000.00', 'delivered');

        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');

        $exitCode = Artisan::call('agent:evaluate-monthly-achievements', [
            'month' => $month,
            '--batch-size' => 10,
        ]);

        $this->assertEquals(0, $exitCode);

        // Verify both agents were evaluated and awarded
        $hlaAwards = AgentAchievementAward::where('agent_id', $this->hla->id)->where('period_key', $month)->count();
        $llaAwards = AgentAchievementAward::where('agent_id', $this->lla->id)->where('period_key', $month)->count();

        // HLA hit 125% -> 2 awards (100% and 125%)
        $this->assertEquals(2, $hlaAwards);
        // LLA hit 100% -> 1 award (100%)
        $this->assertEquals(1, $llaAwards);

        // Single agent evaluation CLI mode test
        $singleExit = Artisan::call('agent:evaluate-monthly-achievements', [
            'month' => $month,
            '--agent-id' => $this->lla->id,
        ]);
        $this->assertEquals(0, $singleExit);
    }

    /**
     * 18. Targeted Test: January qualified, February qualified, March not evaluated, April qualified.
     * Verifies that skipped/missing historical months cannot incorrectly preserve a consecutive streak.
     */
    public function test_historical_streak_gap_month_not_evaluated_breaks_streak(): void
    {
        // Jan 2026: Met
        $this->setTarget($this->lla, '2026-01', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-01', '50000.00', 'delivered');
        $resJan = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-01');
        $this->assertEquals(1, $resJan['streak_months']);

        // Feb 2026: Met
        $this->setTarget($this->lla, '2026-02', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-02', '50000.00', 'delivered');
        $resFeb = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-02');
        $this->assertEquals(2, $resFeb['streak_months']);

        // March 2026: NOT evaluated (skipped / missing award)

        // April 2026: Met
        $this->setTarget($this->lla, '2026-04', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-04', '50000.00', 'delivered');
        $resApr = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-04');

        // March gap strictly breaks the streak -> April resets to 1!
        $this->assertEquals(1, $resApr['streak_months']);

        $streak = AgentAchievementStreak::where('agent_id', $this->lla->id)
            ->where('streak_type', 'target_achievement')
            ->first();

        $this->assertEquals(1, $streak->current_streak);
        $this->assertEquals(2, $streak->longest_streak); // Preserves Jan-Feb peak
        $this->assertEquals('2026-04', $streak->streak_started_period);
        $this->assertEquals('2026-04', $streak->last_qualifying_period);

        // April must NOT receive TARGET_STREAK_3M
        $streakAward = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', '2026-04')
            ->whereHas('achievement', fn($q) => $q->where('code', AgentAchievementService::CODE_TARGET_STREAK_3M))
            ->first();
        $this->assertNull($streakAward);
    }

    /**
     * 19. Targeted Test: January qualified, February missing (not met), March qualified.
     */
    public function test_historical_streak_missing_month_breaks_streak(): void
    {
        // Jan 2026: Met
        $this->setTarget($this->lla, '2026-01', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-01', '50000.00', 'delivered');
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-01');

        // Feb 2026: Target assigned, but zero sales -> Not Met
        $this->setTarget($this->lla, '2026-02', '50000.00');
        $resFeb = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-02');
        $this->assertEquals(0, $resFeb['streak_months']);

        // March 2026: Met
        $this->setTarget($this->lla, '2026-03', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-03', '50000.00', 'delivered');
        $resMar = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-03');

        // Feb break strictly limits March streak to 1
        $this->assertEquals(1, $resMar['streak_months']);

        $streak = AgentAchievementStreak::where('agent_id', $this->lla->id)
            ->where('streak_type', 'target_achievement')
            ->first();

        $this->assertEquals(1, $streak->current_streak);
        $this->assertEquals(1, $streak->longest_streak);
        $this->assertEquals('2026-03', $streak->streak_started_period);
    }

    /**
     * 20. Targeted Test: Out-of-order historical month evaluation.
     * Evaluates March first, then January, then February.
     * Verifies that after all three are evaluated, the state authoritatively reflects the true calendar sequence.
     */
    public function test_out_of_order_historical_month_evaluation_reconciles_authoritatively(): void
    {
        // Set targets and orders for Jan, Feb, Mar
        $this->setTarget($this->lla, '2026-01', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-01', '50000.00', 'delivered');

        $this->setTarget($this->lla, '2026-02', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-02', '50000.00', 'delivered');

        $this->setTarget($this->lla, '2026-03', '50000.00');
        $this->createOrderForAgentInMonth($this->lla, '2026-03', '50000.00', 'delivered');

        // Step 1: Evaluate March FIRST
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-03');

        // Step 2: Evaluate January SECOND
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-01');

        // Step 3: Evaluate February THIRD (completes the contiguous chain 01 -> 02 -> 03)
        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, '2026-02');

        $streak = AgentAchievementStreak::where('agent_id', $this->lla->id)
            ->where('streak_type', 'target_achievement')
            ->first();

        $this->assertNotNull($streak);
        $this->assertEquals(3, $streak->current_streak);
        $this->assertEquals(3, $streak->longest_streak);
        $this->assertEquals('2026-01', $streak->streak_started_period);
        $this->assertEquals('2026-03', $streak->last_qualifying_period);

        // Verify TARGET_STREAK_3M is awarded for 2026-03 despite out-of-order evaluation
        $streakAward = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', '2026-03')
            ->whereHas('achievement', fn($q) => $q->where('code', AgentAchievementService::CODE_TARGET_STREAK_3M))
            ->first();

        $this->assertNotNull($streakAward);
        $this->assertEquals('active', $streakAward->status);
    }

    /**
     * 21. Targeted Test: Recurring streak milestones at 6-month and 9-month cycles.
     * Verifies that each 3-month milestone (3, 6, 9) creates exactly one award and previous awards remain intact.
     */
    public function test_multi_cycle_streak_milestones_at_6_and_9_months(): void
    {
        $months = [
            '2026-01', '2026-02', '2026-03', // Cycle 1 (Month 3)
            '2026-04', '2026-05', '2026-06', // Cycle 2 (Month 6)
            '2026-07', '2026-08', '2026-09', // Cycle 3 (Month 9)
        ];

        foreach ($months as $m) {
            $this->setTarget($this->lla, $m, '50000.00');
            $this->createOrderForAgentInMonth($this->lla, $m, '50000.00', 'delivered');
            $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $m);
        }

        $streak = AgentAchievementStreak::where('agent_id', $this->lla->id)
            ->where('streak_type', 'target_achievement')
            ->first();

        $this->assertEquals(9, $streak->current_streak);
        $this->assertEquals(9, $streak->longest_streak);

        // Distinct awards should exist for 2026-03, 2026-06, and 2026-09
        $awards = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->whereHas('achievement', fn($q) => $q->where('code', AgentAchievementService::CODE_TARGET_STREAK_3M))
            ->orderBy('period_key')
            ->get();

        $this->assertCount(3, $awards);
        $this->assertEquals('2026-03', $awards[0]->period_key);
        $this->assertEquals('3.00', $awards[0]->achieved_value);
        $this->assertEquals('2026-06', $awards[1]->period_key);
        $this->assertEquals('6.00', $awards[1]->achieved_value);
        $this->assertEquals('2026-09', $awards[2]->period_key);
        $this->assertEquals('9.00', $awards[2]->achieved_value);
    }

    /**
     * 22. Targeted Test: Retry achievement notifications command recovers missing notifications.
     */
    public function test_retry_achievement_notifications_command_recovers_missing_notifications(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');
        $this->createOrderForAgentInMonth($this->lla, $month, '100000.00', 'delivered');

        $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        $award = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', $month)
            ->first();

        // Simulate failure: delete notification from database
        DB::table('notifications')->truncate();
        $this->assertEquals(0, DB::table('notifications')->count());

        // Run recovery command
        $exitCode = Artisan::call('agent:retry-achievement-notifications');
        $this->assertEquals(0, $exitCode);

        // Verify notification was reconstructed and delivered
        $expectedNotificationId = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.achievement.award.{$award->id}.monthly_target"
        )->toString();

        $recovered = DB::table('notifications')->where('id', $expectedNotificationId)->first();
        $this->assertNotNull($recovered);

        // Run retry again — must be 100% idempotent
        Artisan::call('agent:retry-achievement-notifications');
        $this->assertEquals(1, DB::table('notifications')->count());
    }

    /**
     * 23. Targeted Test: Closed Colombo month boundaries strictly isolate orders.
     */
    public function test_closed_colombo_month_boundaries_strictly_isolate_orders(): void
    {
        $month = '2026-10';
        $this->setTarget($this->lla, $month, '100000.00');

        // Order 1: 2026-10-31 23:59:59 Colombo (18:29:59 UTC) -> strictly within October Colombo period
        $oct31Utc = Carbon::create(2026, 10, 31, 23, 59, 59, 'Asia/Colombo')->setTimezone('UTC');
        Order::create([
            'order_number' => 'ORD-BOUNDARY-OCT',
            'agent_id' => $this->lla->id,
            'customer_name' => 'Oct Customer',
            'customer_phone' => '+94771234567',
            'shipping_address' => 'Colombo',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => '100000.00',
            'discount_amount' => '0.00',
            'delivery_charge' => '350.00',
            'total_amount' => '100350.00',
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-OCT',
            'placed_at' => $oct31Utc,
            'created_at' => $oct31Utc,
            'updated_at' => $oct31Utc,
        ]);

        // Order 2: 2026-11-01 00:00:01 Colombo (18:30:01 UTC) -> strictly outside October (in November)
        $nov1Utc = Carbon::create(2026, 11, 1, 0, 0, 1, 'Asia/Colombo')->setTimezone('UTC');
        Order::create([
            'order_number' => 'ORD-BOUNDARY-NOV',
            'agent_id' => $this->lla->id,
            'customer_name' => 'Nov Customer',
            'customer_phone' => '+94771234567',
            'shipping_address' => 'Colombo',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => '50000.00',
            'discount_amount' => '0.00',
            'delivery_charge' => '350.00',
            'total_amount' => '50350.00',
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-NOV',
            'placed_at' => $nov1Utc,
            'created_at' => $nov1Utc,
            'updated_at' => $nov1Utc,
        ]);

        $result = $this->achievementService->evaluateMonthlyTargetAchievementsForAgent($this->lla, $month);

        // Net sales must be exactly 100,000.00 (Order 1), strictly excluding Order 2
        $this->assertEquals('100000.00', $result['net_sales']);
        $this->assertEquals('100.00', $result['attainment_pct']);
        $this->assertEquals(1, $result['awarded']);
    }

    /**
     * 24. Targeted Test: Distinct event identities for notification UUIDv5.
     */
    public function test_notification_event_identities_are_distinct(): void
    {
        $award = AgentAchievementAward::create([
            'agent_id' => $this->lla->id,
            'achievement_id' => AgentAchievement::where('code', AgentAchievementService::CODE_FIRST_ORDER)->first()->id,
            'version_id' => 1,
            'period_key' => 'lifetime',
            'achieved_value' => '1.00',
            'threshold_snapshot' => '1.00',
            'status' => 'active',
            'awarded_at' => now(),
        ]);

        $uuidEarned = (new AgentAchievementAwardedNotification($award, 'earned'))->id;
        $uuidCorrected = (new AgentAchievementAwardedNotification($award, 'corrected'))->id;
        $uuidRequalified = (new AgentAchievementAwardedNotification($award, 'requalified'))->id;
        $uuidMonthlyTarget = (new AgentAchievementAwardedNotification($award, 'monthly_target'))->id;
        $uuidStreak = (new AgentAchievementAwardedNotification($award, 'streak'))->id;

        $uuids = [$uuidEarned, $uuidCorrected, $uuidRequalified, $uuidMonthlyTarget, $uuidStreak];
        $this->assertCount(5, array_unique($uuids), 'Notification event UUIDv5 values must all be distinct.');
    }
}
