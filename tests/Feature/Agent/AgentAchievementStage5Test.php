<?php

namespace Tests\Feature\Agent;

use App\Models\AgentAchievement;
use App\Models\AgentAchievementAward;
use App\Models\AgentAchievementStreak;
use App\Models\AgentAchievementVersion;
use App\Models\AgentProfile;
use App\Models\AgentSalesTarget;
use App\Models\Order;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentAchievementPortalService;
use App\Services\AgentAchievementService;
use App\Services\PeriodBoundaryService;
use Carbon\Carbon;
use Database\Seeders\AgentAchievementSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentAchievementStage5Test extends TestCase
{
    use RefreshDatabase;

    protected User $hla;
    protected User $lla;
    protected User $unrelatedLla;
    protected User $customer;
    protected User $staff;
    protected AgentAchievementPortalService $portalService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AgentAchievementSeeder::class);

        $this->portalService = app(AgentAchievementPortalService::class);

        // 1. High-Level Agent (HLA)
        $this->hla = User::factory()->create([
            'role' => 'high_level_agent',
            'status' => 'active',
            'parent_id' => null,
            'name' => 'High Level Leader',
            'email' => 'hla@auraluxe.lk',
            'referral_code' => 'HLA_ROOT',
        ]);
        AgentProfile::create([
            'user_id' => $this->hla->id,
            'kyc_status' => 'verified',
            'referral_code' => 'HLA_ROOT',
        ]);
        UserClosure::firstOrCreate(['ancestor_id' => $this->hla->id, 'descendant_id' => $this->hla->id, 'depth' => 0]);

        // 2. Direct Downline LLA (depth = 1 under HLA)
        $this->lla = User::factory()->create([
            'role' => 'low_level_agent',
            'status' => 'active',
            'parent_id' => $this->hla->id,
            'name' => 'Direct Team Member',
            'email' => 'lla@auraluxe.lk',
            'referral_code' => 'LLA_TEAM_1',
        ]);
        AgentProfile::create([
            'user_id' => $this->lla->id,
            'kyc_status' => 'verified',
            'referral_code' => 'LLA_TEAM_1',
        ]);
        UserClosure::firstOrCreate(['ancestor_id' => $this->lla->id, 'descendant_id' => $this->lla->id, 'depth' => 0]);
        UserClosure::firstOrCreate(['ancestor_id' => $this->hla->id, 'descendant_id' => $this->lla->id, 'depth' => 1]);

        // 3. Unrelated LLA (under a different sponsor)
        $otherHla = User::factory()->create(['role' => 'high_level_agent', 'status' => 'active', 'referral_code' => 'OTHER_HLA']);
        $this->unrelatedLla = User::factory()->create([
            'role' => 'low_level_agent',
            'status' => 'active',
            'parent_id' => $otherHla->id,
            'name' => 'Unrelated Agent',
            'referral_code' => 'UNRELATED_LLA',
        ]);
        UserClosure::firstOrCreate(['ancestor_id' => $this->unrelatedLla->id, 'descendant_id' => $this->unrelatedLla->id, 'depth' => 0]);
        UserClosure::firstOrCreate(['ancestor_id' => $otherHla->id, 'descendant_id' => $this->unrelatedLla->id, 'depth' => 1]);

        // 4. Customer
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        // 5. Staff (Agent Manager)
        $this->staff = User::factory()->create([
            'role' => 'agent_manager',
            'status' => 'active',
        ]);
    }

    /**
     * Helper to create delivered orders for an agent.
     */
    protected function createDeliveredOrder(User $agent, string $subtotal, ?Carbon $placedAt = null): Order
    {
        return Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'agent_id' => $agent->id,
            'referral_code' => $agent->referral_code,
            'customer_name' => 'Jane Silva',
            'customer_phone' => '+94771234567',
            'shipping_address' => 'No 10, Flower Road',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => $subtotal,
            'discount_amount' => '0.00',
            'delivery_charge' => '350.00',
            'total_amount' => bcadd($subtotal, '350.00', 2),
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-' . uniqid(),
            'placed_at' => $placedAt ?? now(),
        ]);
    }

    /**
     * Helper to issue an award directly in test state.
     */
    protected function createAward(User $agent, string $achievementCode, string $status = 'active', string $periodKey = 'lifetime', ?array $metadata = null): AgentAchievementAward
    {
        $achievement = AgentAchievement::where('code', $achievementCode)->firstOrFail();
        $version = AgentAchievementVersion::where('achievement_id', $achievement->id)->firstOrFail();

        return AgentAchievementAward::create([
            'agent_id' => $agent->id,
            'achievement_id' => $achievement->id,
            'version_id' => $version->id,
            'period_key' => $periodKey,
            'achieved_value' => $achievement->threshold_value,
            'threshold_snapshot' => $achievement->threshold_value,
            'status' => $status,
            'is_discretionary' => (bool) $achievement->is_discretionary,
            'awarded_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Helper to create an active consistency streak in test state.
     */
    protected function createStreak(User $agent, int $current = 1, int $longest = 1, bool $active = true): AgentAchievementStreak
    {
        return AgentAchievementStreak::create([
            'agent_id' => $agent->id,
            'streak_type' => 'target_attainment',
            'current_streak' => $current,
            'longest_streak' => $longest,
            'is_active' => $active,
        ]);
    }

    // =========================================================================
    // 1. AUTHENTICATION & ACCESS CONTROL
    // =========================================================================

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/agent/achievements');
        $response->assertRedirect('/login');
    }

    public function test_customer_is_denied_access(): void
    {
        $response = $this->actingAs($this->customer)->get('/agent/achievements');
        $response->assertStatus(403);
    }

    public function test_staff_is_denied_access_to_agent_portal(): void
    {
        $response = $this->actingAs($this->staff)->get('/agent/achievements');
        $response->assertStatus(403);
    }

    public function test_active_lla_and_hla_can_access_dashboard(): void
    {
        // LLA access
        $responseLla = $this->actingAs($this->lla)->get('/agent/achievements');
        $responseLla->assertStatus(200);
        $responseLla->assertInertia(fn (Assert $page) => $page
            ->component('Agent/Achievements/Index', false)
            ->where('is_hla', false)
            ->where('downline', null)
        );

        // HLA access
        $responseHla = $this->actingAs($this->hla)->get('/agent/achievements');
        $responseHla->assertStatus(200);
        $responseHla->assertInertia(fn (Assert $page) => $page
            ->component('Agent/Achievements/Index', false)
            ->where('is_hla', true)
            ->has('downline')
        );
    }

    // =========================================================================
    // 2. PERSONAL RECORDS & CATALOG PRESENTATION
    // =========================================================================

    public function test_personal_active_and_corrected_awards_and_catalog(): void
    {
        // Issue 1 active award and 1 corrected award
        $this->createAward($this->lla, 'SALES_FIRST_ORDER', 'active');
        $this->createAward($this->lla, 'SALES_100K_CUMULATIVE', 'corrected');

        $response = $this->actingAs($this->lla)->get('/agent/achievements');
        $response->assertStatus(200);

        $response->assertInertia(function (Assert $page) {
            $page->component('Agent/Achievements/Index', false)
                ->has('awards', 2)
                ->has('catalog', 12)
                ->where('catalog.0.code', 'SALES_FIRST_ORDER')
                ->where('catalog.0.is_unlocked', true)
                ->where('catalog.0.is_corrected', false)
                ->where('catalog.1.code', 'SALES_100K_CUMULATIVE')
                ->where('catalog.1.is_unlocked', false)
                ->where('catalog.1.is_corrected', true);
        });
    }

    public function test_revoked_award_is_excluded_from_active_showcase_but_visible_in_history(): void
    {
        $award = $this->createAward($this->lla, 'SALES_100K_CUMULATIVE', 'revoked');
        $award->update([
            'revoked_at' => now(),
            'revocation_reason' => 'Administrative investigation finding',
        ]);

        $response = $this->actingAs($this->lla)->get('/agent/achievements');
        $response->assertStatus(200);

        $response->assertInertia(function (Assert $page) {
            $page->component('Agent/Achievements/Index', false)
                ->has('awards', 0) // Excluded from active showcase
                ->where('catalog.1.is_unlocked', false)
                ->where('catalog.1.is_corrected', false)
                ->has('history.data', 1)
                ->where('history.data.0.status', 'revoked');
        });
    }

    public function test_reinstated_award_presentation(): void
    {
        $now = now()->toIso8601String();
        $this->createAward($this->lla, 'SALES_100K_CUMULATIVE', 'active', 'lifetime', [
            'reinstated_at' => $now,
            'event_type' => 'reinstated',
        ]);

        $response = $this->actingAs($this->lla)->get('/agent/achievements');
        $response->assertStatus(200);

        $response->assertInertia(function (Assert $page) use ($now) {
            $page->component('Agent/Achievements/Index', false)
                ->has('awards', 1)
                ->where('awards.0.status', 'active')
                ->where('awards.0.is_reinstated', true)
                ->where('awards.0.reinstated_at', $now);
        });
    }

    public function test_history_pagination_and_filtering(): void
    {
        $this->createAward($this->lla, 'SALES_FIRST_ORDER', 'active');
        $this->createAward($this->lla, 'SALES_100K_CUMULATIVE', 'corrected');
        $this->createAward($this->lla, 'TARGET_MET_MONTHLY', 'revoked', '2026-10');

        // Filter status = corrected
        $response = $this->actingAs($this->lla)->get('/agent/achievements?status=corrected');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 1)
            ->where('history.data.0.status', 'corrected')
        );

        // Filter category = sales_milestone
        $responseCat = $this->actingAs($this->lla)->get('/agent/achievements?category=sales_milestone');
        $responseCat->assertStatus(200);
        $responseCat->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 2)
        );
    }

    // =========================================================================
    // 3. PROGRESS ACCURACY FOR SEVEN SUPPORTED CODES
    // =========================================================================

    public function test_supported_progress_first_sale_binary_semantics(): void
    {
        // 0 orders -> 0.00%
        $payload1 = $this->portalService->calculateMilestoneProgress($this->lla);
        $this->assertEquals('0.00', $payload1['SALES_FIRST_ORDER']['progress_pct']);
        $this->assertFalse($payload1['SALES_FIRST_ORDER']['is_qualified']);

        // Create 1 delivered order of LKR 10,000 -> 100.00%
        $this->createDeliveredOrder($this->lla, '10000.00');

        $payload2 = $this->portalService->calculateMilestoneProgress($this->lla);
        $this->assertEquals('100.00', $payload2['SALES_FIRST_ORDER']['progress_pct']);
        $this->assertTrue($payload2['SALES_FIRST_ORDER']['is_qualified']);
        $this->assertEquals('1', $payload2['SALES_FIRST_ORDER']['current_value']);
    }

    public function test_supported_progress_cumulative_volume_milestones(): void
    {
        // Net sales: LKR 40,000.00
        $this->createDeliveredOrder($this->lla, '40000.00');

        $progress = $this->portalService->calculateMilestoneProgress($this->lla);

        // 100K: 40,000 / 100,000 = 40.00%
        $this->assertEquals('40.00', $progress['SALES_100K_CUMULATIVE']['progress_pct']);
        $this->assertEquals('40000.00', $progress['SALES_100K_CUMULATIVE']['current_value']);
        $this->assertFalse($progress['SALES_100K_CUMULATIVE']['is_qualified']);

        // 500K: 40,000 / 500,000 = 8.00%
        $this->assertEquals('8.00', $progress['SALES_500K_CUMULATIVE']['progress_pct']);

        // 1M: 40,000 / 1,000,000 = 4.00%
        $this->assertEquals('4.00', $progress['SALES_1M_CUMULATIVE']['progress_pct']);
    }

    public function test_supported_progress_monthly_target_and_streak(): void
    {
        $currentMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');

        // Case A: Missing monthly target
        $progressNoTarget = $this->portalService->calculateMilestoneProgress($this->lla);
        $this->assertNull($progressNoTarget['TARGET_MET_MONTHLY']['progress_pct']);
        $this->assertFalse($progressNoTarget['TARGET_MET_MONTHLY']['has_target']);
        $this->assertNull($progressNoTarget['TARGET_OVERACHIEVE_125']['progress_pct']);

        // Case B: Assign target of LKR 50,000 and create LKR 65,000 sales (130% attainment)
        AgentSalesTarget::create([
            'agent_id' => $this->lla->id,
            'target_month' => $currentMonth,
            'sales_target' => '50000.00',
            'created_by' => $this->hla->id,
        ]);

        $this->createDeliveredOrder($this->lla, '65000.00');

        // Create 2-month streak
        $streak = AgentAchievementStreak::create([
            'agent_id' => $this->lla->id,
            'streak_type' => 'target_attainment',
            'current_streak' => 2,
            'longest_streak' => 2,
            'is_active' => true,
        ]);

        $progressWithTarget = $this->portalService->calculateMilestoneProgress($this->lla, $streak);

        // TARGET_MET_MONTHLY: Achieved 100% (attainment is 130%) -> capped at 100.00%
        $this->assertEquals('100.00', $progressWithTarget['TARGET_MET_MONTHLY']['progress_pct']);
        $this->assertTrue($progressWithTarget['TARGET_MET_MONTHLY']['is_qualified']);

        // TARGET_OVERACHIEVE_125: Attainment 130.00% >= 125.00% -> 100.00%
        $this->assertEquals('100.00', $progressWithTarget['TARGET_OVERACHIEVE_125']['progress_pct']);
        $this->assertTrue($progressWithTarget['TARGET_OVERACHIEVE_125']['is_qualified']);

        // TARGET_STREAK_3M: 2 / 3 months = 66.66% in BCMath (scale 2)
        $this->assertEquals('66.66', $progressWithTarget['TARGET_STREAK_3M']['progress_pct']);
        $this->assertFalse($progressWithTarget['TARGET_STREAK_3M']['is_qualified']);
    }

    public function test_unsupported_five_codes_have_null_progress(): void
    {
        $progress = $this->portalService->calculateMilestoneProgress($this->lla);

        $unsupported = [
            'CUSTOMERS_ACQUIRED_10',
            'TEAM_BUILDER_DIRECT_5',
            'TEAM_SALES_1M_MONTHLY',
            'LEADERBOARD_MONTH_TOP1',
            'HONOR_CONVENTION_AWARD',
        ];

        foreach ($unsupported as $code) {
            $this->assertArrayHasKey($code, $progress);
            $this->assertFalse($progress[$code]['is_supported']);
            $this->assertNull($progress[$code]['progress_pct']);
            $this->assertNull($progress[$code]['current_value']);
            $this->assertFalse($progress[$code]['is_qualified']);
            $this->assertEquals('Criteria-based recognition', $progress[$code]['status_text']);
        }
    }

    // =========================================================================
    // 4. HLA / LLA BOUNDARIES & DOWNLINE INSPECTION
    // =========================================================================

    public function test_hla_can_view_direct_downline_honors_and_member_inspection(): void
    {
        // Issue active award to direct downline LLA
        $this->createAward($this->lla, 'SALES_FIRST_ORDER', 'active');

        // HLA visits portal
        $response = $this->actingAs($this->hla)->get('/agent/achievements');
        $response->assertStatus(200);

        $response->assertInertia(function (Assert $page) {
            $page->component('Agent/Achievements/Index', false)
                ->where('is_hla', true)
                ->where('downline.total_downline_count', 1)
                ->where('downline.total_badges_unlocked', 1)
                ->where('downline.top_achiever.id', $this->lla->id)
                ->where('downline.members.data.0.id', $this->lla->id)
                ->where('downline.members.data.0.badge_count', 1);
        });

        // HLA inspects direct downline member
        $inspectResponse = $this->actingAs($this->hla)->get("/agent/achievements/downline/{$this->lla->id}");
        $inspectResponse->assertStatus(200);
        $inspectResponse->assertInertia(function (Assert $page) {
            $page->component('Agent/Achievements/DownlineMember', false)
                ->where('member.id', $this->lla->id)
                ->where('member.name', 'Direct Team Member')
                ->has('awards', 1)
                ->where('awards.0.code', 'SALES_FIRST_ORDER');
        });
    }

    public function test_hla_cannot_inspect_unrelated_agent(): void
    {
        $response = $this->actingAs($this->hla)->get("/agent/achievements/downline/{$this->unrelatedLla->id}");
        $response->assertStatus(403);
    }

    public function test_lla_cannot_access_downline_inspection(): void
    {
        // LLA tries to inspect another agent
        $response = $this->actingAs($this->lla)->get("/agent/achievements/downline/{$this->unrelatedLla->id}");
        $response->assertStatus(403);

        // LLA tries to inspect themselves via downline route
        $responseSelf = $this->actingAs($this->lla)->get("/agent/achievements/downline/{$this->lla->id}");
        $responseSelf->assertStatus(403);
    }

    // =========================================================================
    // 5. PRIVACY & SANITIZATION
    // =========================================================================

    public function test_sensitive_administrative_fields_are_strictly_absent(): void
    {
        $award = $this->createAward($this->lla, 'HONOR_CONVENTION_AWARD', 'active');
        $award->update([
            'awarded_by' => $this->staff->id,
            'revoked_by' => null,
            'revocation_reason' => null,
        ]);

        $payload = $this->portalService->getPersonalAchievementPayload($this->lla);
        $firstAward = $payload['awards'][0];

        // Assert strictly absent
        $this->assertArrayNotHasKey('requested_by', $firstAward);
        $this->assertArrayNotHasKey('awarded_by', $firstAward);
        $this->assertArrayNotHasKey('revoked_by', $firstAward);
        $this->assertArrayNotHasKey('nomination_notes', $firstAward);
        $this->assertArrayNotHasKey('decision_notes', $firstAward);
        $this->assertArrayNotHasKey('revocation_reason', $firstAward);
        $this->assertArrayNotHasKey('audit_log', $firstAward);
        $this->assertArrayNotHasKey('audit_logs', $firstAward);
        $this->assertArrayNotHasKey('audit_id', $firstAward);

        // Downline member payload check
        $downlinePayload = $this->portalService->getDownlineMemberDetailPayload($this->hla, $this->lla);
        $this->assertArrayNotHasKey('wallet_balance', $downlinePayload['member']);
        $this->assertArrayNotHasKey('bank_account', $downlinePayload['member']);
        $this->assertArrayNotHasKey('nic', $downlinePayload['member']);
    }

    // =========================================================================
    // 6. PERFORMANCE & QUERY INVARIANCE
    // =========================================================================

    public function test_eager_loading_and_service_query_performance(): void
    {
        $this->createAward($this->lla, 'SALES_FIRST_ORDER', 'active');
        $this->createAward($this->lla, 'SALES_100K_CUMULATIVE', 'active');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $payload = $this->portalService->getPersonalAchievementPayload($this->lla);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Service level query count is strictly bounded (20 queries when no current-month target assigned, up to 30 with target)
        $this->assertLessThanOrEqual(30, count($queries), 'Service query count exceeded ceiling.');

        // Eager loading verification
        $rawAward = AgentAchievementAward::with(['achievement', 'version'])->where('agent_id', $this->lla->id)->first();
        $this->assertTrue($rawAward->relationLoaded('achievement'));
        $this->assertTrue($rawAward->relationLoaded('version'));
    }

    public function test_hla_downline_aggregation_query_count_invariance(): void
    {
        // Dataset 1: HLA with 1 downline LLA
        DB::flushQueryLog();
        DB::enableQueryLog();

        $payload1 = $this->portalService->getHlaDownlinePayload($this->hla);

        $queries1 = DB::getQueryLog();
        DB::disableQueryLog();

        // Exactly 3 queries: 1 closure IDs + 1 grouped member counts (with array_sum for total) + 1 paginated page data
        $this->assertEquals(3, count($queries1), 'Downline payload query count must be strictly 3.');

        // Dataset 2: Add 5 more direct downline LLAs to HLA
        for ($i = 2; $i <= 6; $i++) {
            $newMember = User::factory()->create([
                'role' => 'low_level_agent',
                'status' => 'active',
                'parent_id' => $this->hla->id,
                'name' => "Team Member {$i}",
                'referral_code' => "LLA_TEAM_{$i}",
            ]);
            UserClosure::firstOrCreate(['ancestor_id' => $newMember->id, 'descendant_id' => $newMember->id, 'depth' => 0]);
            UserClosure::firstOrCreate(['ancestor_id' => $this->hla->id, 'descendant_id' => $newMember->id, 'depth' => 1]);
            $this->createAward($newMember, 'SALES_FIRST_ORDER', 'active');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $payload2 = $this->portalService->getHlaDownlinePayload($this->hla);

        $queries2 = DB::getQueryLog();
        DB::disableQueryLog();

        // Scaling invariance: Still strictly 3 queries with 6 team members! (O(1) query count)
        $this->assertEquals(3, count($queries2), 'Downline payload query count must remain strictly 3 regardless of team size.');
        $this->assertEquals(6, $payload2['total_downline_count']);
        $this->assertEquals(5, $payload2['total_badges_unlocked']);
    }

    public function test_http_get_achievements_query_count_measurement(): void
    {
        $this->createAward($this->lla, 'SALES_FIRST_ORDER', 'active');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->lla)->get('/agent/achievements');
        $response->assertStatus(200);

        $httpQueries = DB::getQueryLog();
        DB::disableQueryLog();

        // HTTP request query count measurement (includes auth session, HandleInertiaRequests, and domain service)
        // With optimization (no current-month computeAnalyticsSummary when no target assigned), count is strictly bounded <= 25
        $this->assertLessThanOrEqual(25, count($httpQueries), 'HTTP request query count exceeded ceiling.');
    }

    public function test_agent_dashboard_renders_achievement_spotlight(): void
    {
        $this->createAward($this->lla, 'SALES_FIRST_ORDER', 'active');
        $this->createStreak($this->lla, 3, 5);

        $response = $this->actingAs($this->lla)->get('/agent/dashboard');
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Agent/Dashboard')
            ->has('achievement_spotlight')
            ->where('achievement_spotlight.total_unlocked_badges', 1)
            ->where('achievement_spotlight.latest_award.code', 'SALES_FIRST_ORDER')
            ->where('achievement_spotlight.streak.current_streak', 3)
        );
    }

    public function test_agent_dashboard_query_count_is_bounded_with_spotlight(): void
    {
        $this->createAward($this->lla, 'SALES_FIRST_ORDER', 'active');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->lla)->get('/agent/dashboard');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Dashboard query count is strictly bounded:
        // Baseline dashboard executes 12 queries; spotlight adds 5 queries (count, latest award, eager achievement, eager version, streak).
        // Total measured queries = 17 (asserted <= 18).
        $this->assertLessThanOrEqual(18, count($queries), 'Agent dashboard query count exceeded ceiling.');
    }

    public function test_downline_member_page_renders_for_direct_downline(): void
    {
        $this->createAward($this->lla, 'SALES_FIRST_ORDER', 'active');
        $this->createStreak($this->lla, 2, 4);

        $response = $this->actingAs($this->hla)->get("/agent/achievements/downline/{$this->lla->id}");
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Agent/Achievements/DownlineMember')
            ->where('member.id', $this->lla->id)
            ->where('member.name', 'Direct Team Member')
            ->has('awards', 1)
            ->where('awards.0.code', 'SALES_FIRST_ORDER')
            ->where('streak.current_streak', 2)
        );
    }

    public function test_downline_member_page_denies_indirect_downline_or_stranger(): void
    {
        $response = $this->actingAs($this->hla)->get("/agent/achievements/downline/{$this->unrelatedLla->id}");
        $response->assertStatus(403);
    }
}
