<?php

namespace App\Services;

use App\Models\AgentAchievement;
use App\Models\AgentAchievementAward;
use App\Models\AgentAchievementStreak;
use App\Models\AgentSalesTarget;
use App\Models\User;
use App\Models\UserClosure;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AgentAchievementPortalService
{
    /**
     * Seven achievement codes actively supported with automated progress tracking.
     */
    public const SUPPORTED_PROGRESS_CODES = [
        AgentAchievementService::CODE_FIRST_ORDER,
        AgentAchievementService::CODE_100K,
        AgentAchievementService::CODE_500K,
        AgentAchievementService::CODE_1M,
        AgentAchievementService::CODE_TARGET_MET,
        AgentAchievementService::CODE_TARGET_OVERACHIEVE_125,
        AgentAchievementService::CODE_TARGET_STREAK_3M,
    ];

    public function __construct(
        protected AgentAchievementService $achievementService,
        protected AgentAnalyticsService $analyticsService,
        protected PeriodBoundaryService $boundaryService,
        protected AgentHierarchyService $hierarchyService
    ) {}

    /**
     * Build the complete personal achievement payload for the authenticated agent.
     *
     * @param User $agent
     * @param array{status?: ?string, category?: ?string, page?: int|string, tab?: ?string} $filters
     * @return array<string, mixed>
     */
    public function getPersonalAchievementPayload(User $agent, array $filters = []): array
    {
        // 1. Fetch personal active and corrected awards with version snapshots
        $awards = AgentAchievementAward::with(['achievement', 'version'])
            ->where('agent_id', $agent->id)
            ->whereIn('status', ['active', 'corrected'])
            ->orderByDesc('awarded_at')
            ->get();

        // 2. Fetch full catalog of active definitions
        $catalog = AgentAchievement::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // 3. Fetch personal streak record
        $streak = AgentAchievementStreak::where('agent_id', $agent->id)
            ->where('streak_type', 'target_attainment')
            ->first();

        // 4. Calculate progress for the 7 supported codes
        $progress = $this->calculateMilestoneProgress($agent, $streak);

        // 5. Fetch paginated personal award history (including active, corrected, revoked)
        $historyQuery = AgentAchievementAward::with(['achievement', 'version'])
            ->where('agent_id', $agent->id);

        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'corrected', 'revoked'], true)) {
            $historyQuery->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $historyQuery->whereHas('achievement', fn ($q) => $q->where('category', $filters['category']));
        }

        $historyPaginator = $historyQuery->orderByDesc('awarded_at')->paginate(15);
        $historyPaginator->through(fn ($award) => $this->transformAward($award));

        // 6. If High-Level Agent, fetch direct downline honors summary
        $downline = null;
        if ($agent->isHighLevelAgent()) {
            $downline = $this->getHlaDownlinePayload($agent);
        }

        return [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'referral_code' => $agent->referral_code,
                'role' => $agent->role->value ?? (string) $agent->role,
                'is_hla' => $agent->isHighLevelAgent(),
            ],
            'awards' => $awards->map(fn ($award) => $this->transformAward($award))->values()->all(),
            'catalog' => $this->transformCatalog($catalog, $awards),
            'streak' => $streak ? [
                'current_streak' => (int) $streak->current_streak,
                'longest_streak' => (int) $streak->longest_streak,
                'last_qualifying_period' => $streak->last_qualifying_period,
                'streak_started_period' => $streak->streak_started_period,
                'is_active' => (bool) $streak->is_active,
            ] : null,
            'progress' => $progress,
            'history' => $historyPaginator,
            'filters' => [
                'status' => $filters['status'] ?? null,
                'category' => $filters['category'] ?? null,
                'tab' => $filters['tab'] ?? 'showcase',
            ],
            'is_hla' => $agent->isHighLevelAgent(),
            'downline' => $downline,
        ];
    }

    /**
     * Calculate reliable progress toward the seven engine-supported achievements.
     * The remaining 5 achievements receive null progress with is_supported = false.
     *
     * @param User $agent
     * @param AgentAchievementStreak|null $streak
     * @return array<string, array<string, mixed>>
     */
    public function calculateMilestoneProgress(User $agent, ?AgentAchievementStreak $streak = null): array
    {
        // 1. Lifetime metrics (Orders, Sales, Refunds) via authoritative engine
        $lifetimeMetrics = $this->achievementService->getAgentLifetimeMetrics($agent);
        $netSales = AgentAnalyticsService::normalizeDecimalString($lifetimeMetrics['net_product_sales'], true);
        $totalFulfilled = (int) $lifetimeMetrics['total_fulfilled_orders'];

        // 2. Current Month Target & Sales
        $currentMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
        $targetRecord = AgentSalesTarget::where('agent_id', $agent->id)
            ->where('target_month', $currentMonth)
            ->first();

        $targetAmount = $targetRecord ? AgentAnalyticsService::normalizeDecimalString($targetRecord->sales_target, false) : null;
        $hasPositiveTarget = $targetAmount !== null && bccomp($targetAmount, '0.00', 2) > 0;

        $currentMonthNetSales = '0.00';
        $attainmentPct = null;

        // Only query current month sales if an authoritative positive target exists
        if ($hasPositiveTarget) {
            $startColombo = Carbon::createFromFormat('Y-m', $currentMonth, PeriodBoundaryService::TIMEZONE)->startOfMonth();
            $endColombo = $startColombo->copy()->addMonthNoOverflow()->startOfMonth();
            $startUtc = $startColombo->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
            $endUtc = $endColombo->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');

            $currentMonthSummary = $this->analyticsService->computeAnalyticsSummary(
                agentIds: $agent->id,
                referralCodes: $agent->referral_code,
                startUtc: $startUtc,
                endUtc: $endUtc,
                salesTargetAmount: $targetAmount
            );

            $currentMonthNetSales = AgentAnalyticsService::normalizeDecimalString($currentMonthSummary['net_product_sales'], true);
            $attainmentPct = bcmul(bcdiv(max('0.00', $currentMonthNetSales), $targetAmount, 4), '100', 2);
        }

        // 3. Build progress map for the 7 supported codes
        $progress = [];

        // Code 1: SALES_FIRST_ORDER (Binary: 100.00% or 0.00%)
        $firstSaleQualified = $totalFulfilled >= 1 && bccomp($netSales, '0.00', 2) > 0;
        $progress[AgentAchievementService::CODE_FIRST_ORDER] = [
            'code' => AgentAchievementService::CODE_FIRST_ORDER,
            'is_supported' => true,
            'progress_pct' => $firstSaleQualified ? '100.00' : '0.00',
            'current_value' => (string) $totalFulfilled,
            'threshold_value' => '1.00',
            'unit' => 'orders',
            'is_qualified' => $firstSaleQualified,
            'status_text' => $firstSaleQualified ? 'Milestone Achieved' : ($totalFulfilled > 0 ? 'Pending positive net commercial sales' : '0 of 1 delivered orders completed'),
        ];

        // Code 2: SALES_100K_CUMULATIVE
        $p100k = min('100.00', bcmul(bcdiv(max('0.00', $netSales), '100000.00', 4), '100', 2));
        $progress[AgentAchievementService::CODE_100K] = [
            'code' => AgentAchievementService::CODE_100K,
            'is_supported' => true,
            'progress_pct' => $p100k,
            'current_value' => $netSales,
            'threshold_value' => '100000.00',
            'unit' => 'LKR',
            'is_qualified' => bccomp($p100k, '100.00', 2) >= 0,
            'status_text' => bccomp($p100k, '100.00', 2) >= 0 ? 'Milestone Achieved' : "LKR {$netSales} of LKR 100,000.00 ({$p100k}%)",
        ];

        // Code 3: SALES_500K_CUMULATIVE
        $p500k = min('100.00', bcmul(bcdiv(max('0.00', $netSales), '500000.00', 4), '100', 2));
        $progress[AgentAchievementService::CODE_500K] = [
            'code' => AgentAchievementService::CODE_500K,
            'is_supported' => true,
            'progress_pct' => $p500k,
            'current_value' => $netSales,
            'threshold_value' => '500000.00',
            'unit' => 'LKR',
            'is_qualified' => bccomp($p500k, '100.00', 2) >= 0,
            'status_text' => bccomp($p500k, '100.00', 2) >= 0 ? 'Milestone Achieved' : "LKR {$netSales} of LKR 500,000.00 ({$p500k}%)",
        ];

        // Code 4: SALES_1M_CUMULATIVE
        $p1m = min('100.00', bcmul(bcdiv(max('0.00', $netSales), '1000000.00', 4), '100', 2));
        $progress[AgentAchievementService::CODE_1M] = [
            'code' => AgentAchievementService::CODE_1M,
            'is_supported' => true,
            'progress_pct' => $p1m,
            'current_value' => $netSales,
            'threshold_value' => '1000000.00',
            'unit' => 'LKR',
            'is_qualified' => bccomp($p1m, '100.00', 2) >= 0,
            'status_text' => bccomp($p1m, '100.00', 2) >= 0 ? 'Milestone Achieved' : "LKR {$netSales} of LKR 1,000,000.00 ({$p1m}%)",
        ];

        // Code 5: TARGET_MET_MONTHLY
        $pTargetMet = $hasPositiveTarget ? min('100.00', $attainmentPct) : null;
        $progress[AgentAchievementService::CODE_TARGET_MET] = [
            'code' => AgentAchievementService::CODE_TARGET_MET,
            'is_supported' => true,
            'progress_pct' => $pTargetMet,
            'current_value' => $currentMonthNetSales,
            'threshold_value' => $targetAmount,
            'unit' => 'LKR',
            'target_month' => $currentMonth,
            'has_target' => $hasPositiveTarget,
            'is_qualified' => $hasPositiveTarget && bccomp($attainmentPct, '100.00', 2) >= 0,
            'status_text' => $hasPositiveTarget
                ? (bccomp($attainmentPct, '100.00', 2) >= 0 ? 'Monthly Target Achieved' : "{$attainmentPct}% of LKR {$targetAmount} target")
                : 'No monthly target assigned for ' . $currentMonth,
        ];

        // Code 6: TARGET_OVERACHIEVE_125
        $pTarget125 = $hasPositiveTarget ? min('100.00', bcmul(bcdiv(max('0.00', $attainmentPct), '125.00', 4), '100', 2)) : null;
        $progress[AgentAchievementService::CODE_TARGET_OVERACHIEVE_125] = [
            'code' => AgentAchievementService::CODE_TARGET_OVERACHIEVE_125,
            'is_supported' => true,
            'progress_pct' => $pTarget125,
            'current_value' => $attainmentPct ? "{$attainmentPct}%" : '0.00%',
            'threshold_value' => '125.00%',
            'unit' => '%',
            'target_month' => $currentMonth,
            'has_target' => $hasPositiveTarget,
            'is_qualified' => $hasPositiveTarget && bccomp($attainmentPct, '125.00', 2) >= 0,
            'status_text' => $hasPositiveTarget
                ? (bccomp($attainmentPct, '125.00', 2) >= 0 ? '125% Overachievement Unlocked' : "Attainment: {$attainmentPct}% (Target: 125.00%)")
                : 'No monthly target assigned for ' . $currentMonth,
        ];

        // Code 7: TARGET_STREAK_3M
        $currentStreak = (int) ($streak?->current_streak ?? 0);
        $pStreak = min('100.00', bcmul(bcdiv((string) $currentStreak, '3', 4), '100', 2));
        $progress[AgentAchievementService::CODE_TARGET_STREAK_3M] = [
            'code' => AgentAchievementService::CODE_TARGET_STREAK_3M,
            'is_supported' => true,
            'progress_pct' => $pStreak,
            'current_value' => (string) $currentStreak,
            'threshold_value' => '3',
            'unit' => 'months',
            'is_qualified' => $currentStreak >= 3,
            'status_text' => $currentStreak >= 3 ? '3-Month Streak Achieved' : "{$currentStreak} of 3 consecutive qualifying months",
        ];

        // 4. Remaining 5 codes are explicitly non-supported for automated progress
        $unsupportedCodes = [
            'CUSTOMERS_ACQUIRED_10',
            'TEAM_BUILDER_DIRECT_5',
            'TEAM_SALES_1M_MONTHLY',
            'LEADERBOARD_MONTH_TOP1',
            'HONOR_CONVENTION_AWARD',
        ];

        foreach ($unsupportedCodes as $unsupportedCode) {
            $progress[$unsupportedCode] = [
                'code' => $unsupportedCode,
                'is_supported' => false,
                'progress_pct' => null,
                'current_value' => null,
                'threshold_value' => null,
                'unit' => null,
                'is_qualified' => false,
                'status_text' => 'Criteria-based recognition',
            ];
        }

        return $progress;
    }

    /**
     * Retrieve aggregated direct downline honors for a High-Level Agent.
     * Enforces single-tier direct downline (depth = 1).
     *
     * @param User $hla
     * @param int $perPage
     * @return array<string, mixed>
     */
    public function getHlaDownlinePayload(User $hla, int $perPage = 15): array
    {
        if (!$hla->isHighLevelAgent()) {
            throw new HttpException(403, 'Unauthorized: Downline achievement honors are restricted to High-Level Agents.');
        }

        // 1. Direct downline LLAs (depth = 1)
        $directDownlineIds = UserClosure::where('ancestor_id', $hla->id)
            ->where('depth', 1)
            ->pluck('descendant_id')
            ->all();

        if (empty($directDownlineIds)) {
            return [
                'total_downline_count' => 0,
                'total_badges_unlocked' => 0,
                'top_achiever' => null,
                'members' => new LengthAwarePaginator([], 0, $perPage),
            ];
        }

        // 2. Query badge counts per member (Single grouped aggregation query - Query 2)
        $badgeCounts = AgentAchievementAward::whereIn('agent_id', $directDownlineIds)
            ->where('status', 'active')
            ->selectRaw('agent_id, count(*) as count')
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id')
            ->all();

        // Total team badges derived cleanly from grouped counts via array_sum (0 additional queries)
        $totalBadgesUnlocked = array_sum($badgeCounts);

        // 3. Paginated direct downline members (Single page data query - Query 3)
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $offset = ($currentPage - 1) * $perPage;
        $pageIds = array_slice($directDownlineIds, $offset, $perPage);

        $members = empty($pageIds) ? collect() : User::whereIn('id', $pageIds)
            ->select(['id', 'name', 'referral_code', 'role', 'status', 'created_at'])
            ->orderBy('name')
            ->get();

        $topAchiever = null;
        $maxBadges = 0;

        $transformedMembers = $members->map(function ($member) use ($badgeCounts, &$topAchiever, &$maxBadges) {
            $count = (int) ($badgeCounts[$member->id] ?? 0);
            if ($count > $maxBadges) {
                $maxBadges = $count;
                $topAchiever = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'referral_code' => $member->referral_code,
                    'badge_count' => $count,
                ];
            }

            return [
                'id' => $member->id,
                'name' => $member->name,
                'referral_code' => $member->referral_code,
                'role' => $member->role->value ?? (string) $member->role,
                'status' => $member->status->value ?? (string) $member->status,
                'badge_count' => $count,
                'joined_at' => $member->created_at?->toIso8601String(),
            ];
        });

        // Ensure overall top achiever is identified even if located on another page
        if (!empty($badgeCounts) && ($topAchiever === null || $maxBadges === 0)) {
            arsort($badgeCounts);
            $topAgentId = array_key_first($badgeCounts);
            $topUser = $members->firstWhere('id', $topAgentId);
            if ($topUser) {
                $topAchiever = [
                    'id' => $topUser->id,
                    'name' => $topUser->name,
                    'referral_code' => $topUser->referral_code,
                    'badge_count' => (int) $badgeCounts[$topAgentId],
                ];
            }
        }

        $membersPaginator = new LengthAwarePaginator(
            $transformedMembers,
            count($directDownlineIds),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'total_downline_count' => count($directDownlineIds),
            'total_badges_unlocked' => $totalBadgesUnlocked,
            'top_achiever' => $topAchiever,
            'members' => $membersPaginator,
        ];
    }

    /**
     * Retrieve sanitized achievement records for an authorized direct downline member (HLA exclusive).
     *
     * @param User $hla
     * @param User $member
     * @return array<string, mixed>
     */
    public function getDownlineMemberDetailPayload(User $hla, User $member): array
    {
        if (!$hla->isHighLevelAgent()) {
            throw new HttpException(403, 'Unauthorized: Downline achievement inspection is restricted to High-Level Agents.');
        }

        // Enforce direct downline relationship (depth = 1)
        $isDirectDownline = UserClosure::where('ancestor_id', $hla->id)
            ->where('descendant_id', $member->id)
            ->where('depth', 1)
            ->exists();

        if (!$isDirectDownline) {
            throw new HttpException(403, 'Unauthorized: Agent is not a verified direct downline member of your network.');
        }

        // Active awards only (HLA sees active achievements of downline)
        $awards = AgentAchievementAward::with(['achievement', 'version'])
            ->where('agent_id', $member->id)
            ->where('status', 'active')
            ->orderByDesc('awarded_at')
            ->get();

        $streak = AgentAchievementStreak::where('agent_id', $member->id)
            ->where('streak_type', 'target_attainment')
            ->first();

        return [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'referral_code' => $member->referral_code,
                'role' => $member->role->value ?? (string) $member->role,
                'status' => $member->status->value ?? (string) $member->status,
            ],
            'awards' => $awards->map(fn ($award) => $this->transformAward($award))->values()->all(),
            'streak' => $streak ? [
                'current_streak' => (int) $streak->current_streak,
                'longest_streak' => (int) $streak->longest_streak,
                'last_qualifying_period' => $streak->last_qualifying_period,
                'is_active' => (bool) $streak->is_active,
            ] : null,
        ];
    }

    /**
     * Transform an AgentAchievementAward Eloquent model into a strictly sanitized frontend-safe DTO.
     * Excludes: requested_by, awarded_by, revoked_by, nomination_notes, decision_notes, revocation_reason, AuditLog.
     *
     * @param AgentAchievementAward $award
     * @return array<string, mixed>
     */
    public function transformAward(AgentAchievementAward $award): array
    {
        $achievement = $award->achievement;
        $version = $award->version;

        $isReinstated = isset($award->metadata['reinstated_at']) || ($award->metadata['event_type'] ?? null) === 'reinstated';
        $reinstatedAt = $award->metadata['reinstated_at'] ?? null;

        return [
            'id' => $award->id,
            'achievement_id' => $award->achievement_id,
            'code' => $achievement?->code,
            'name' => $achievement?->name,
            'description' => $achievement?->description,
            'category' => $achievement?->category,
            'badge_tier' => $achievement?->badge_tier,
            'badge_icon' => $achievement?->badge_icon,
            'metric_type' => $achievement?->metric_type,
            'period_key' => $award->period_key,
            'achieved_value' => (string) $award->achieved_value,
            'threshold_snapshot' => (string) $award->threshold_snapshot,
            'status' => $award->status,
            'is_discretionary' => (bool) $award->is_discretionary,
            'is_reinstated' => $isReinstated,
            'reinstated_at' => $reinstatedAt,
            'awarded_at' => $award->awarded_at?->toIso8601String(),
            'revoked_at' => $award->revoked_at?->toIso8601String(),
            'version' => $version?->version,
            'qualification_rules_snapshot' => $version?->qualification_rules_snapshot,
        ];
    }

    /**
     * Transform catalog definitions with lock/unlock status for the authenticated agent.
     *
     * @param Collection<int, AgentAchievement> $catalog
     * @param Collection<int, AgentAchievementAward> $activeAwards
     * @return array<int, array<string, mixed>>
     */
    public function transformCatalog(Collection $catalog, Collection $activeAwards): array
    {
        $awardsByAchievementId = $activeAwards->keyBy('achievement_id');

        return $catalog->map(function (AgentAchievement $item) use ($awardsByAchievementId) {
            $award = $awardsByAchievementId->get($item->id);
            $isUnlocked = $award && $award->status === 'active';
            $isCorrected = $award && $award->status === 'corrected';

            return [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'description' => $item->description,
                'category' => $item->category,
                'badge_tier' => $item->badge_tier,
                'badge_icon' => $item->badge_icon,
                'metric_type' => $item->metric_type,
                'threshold_value' => (string) $item->threshold_value,
                'period_type' => $item->period_type,
                'is_recurring' => (bool) $item->is_recurring,
                'is_discretionary' => (bool) $item->is_discretionary,
                'is_unlocked' => $isUnlocked,
                'is_corrected' => $isCorrected,
                'award_id' => $award?->id,
                'awarded_at' => $award?->awarded_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * Retrieve lightweight achievement spotlight telemetry for the main Agent Dashboard.
     * Executes strictly bounded indexed queries (3 to 5 fast queries).
     * Does NOT trigger heavy lifetime analytics or commission recalculations.
     *
     * @param User $agent
     * @return array<string, mixed>
     */
    public function getDashboardSpotlightPayload(User $agent): array
    {
        // 1. Active unlocked badges count (uses index ['agent_id', 'status', 'awarded_at'])
        $unlockedCount = AgentAchievementAward::where('agent_id', $agent->id)
            ->where('status', 'active')
            ->count();

        // 2. Single latest active award (uses index ['agent_id', 'status', 'awarded_at'] backward scan)
        $latestAward = AgentAchievementAward::with(['achievement', 'version'])
            ->where('agent_id', $agent->id)
            ->where('status', 'active')
            ->orderByDesc('awarded_at')
            ->first();

        // 3. Active target attainment streak (uses unique key ['agent_id', 'streak_type'])
        $streak = AgentAchievementStreak::where('agent_id', $agent->id)
            ->where('streak_type', 'target_attainment')
            ->first();

        return [
            'total_unlocked_badges' => $unlockedCount,
            'latest_award' => $latestAward ? $this->transformAward($latestAward) : null,
            'streak' => $streak ? [
                'current_streak' => (int) $streak->current_streak,
                'longest_streak' => (int) $streak->longest_streak,
                'is_active' => (bool) $streak->is_active,
            ] : null,
        ];
    }
}


