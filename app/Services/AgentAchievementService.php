<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentAchievement;
use App\Models\AgentAchievementAward;
use App\Models\AgentAchievementEvaluationRun;
use App\Models\AgentAchievementRequest;
use App\Models\AgentAchievementStreak;
use App\Models\AgentAchievementVersion;
use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AgentAchievementAwardedNotification;
use App\Notifications\AgentAchievementReinstatedNotification;
use App\Notifications\AgentAchievementRevokedNotification;
use Carbon\Carbon;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class AgentAchievementService
{
    public const CODE_FIRST_ORDER = 'SALES_FIRST_ORDER';
    public const CODE_100K = 'SALES_100K_CUMULATIVE';
    public const CODE_500K = 'SALES_500K_CUMULATIVE';
    public const CODE_1M = 'SALES_1M_CUMULATIVE';

    public const CUMULATIVE_CODES = [
        self::CODE_FIRST_ORDER,
        self::CODE_100K,
        self::CODE_500K,
        self::CODE_1M,
    ];

    public const CODE_TARGET_MET = 'TARGET_MET_MONTHLY';
    public const CODE_TARGET_OVERACHIEVE_125 = 'TARGET_OVERACHIEVE_125';
    public const CODE_TARGET_STREAK_3M = 'TARGET_STREAK_3M';

    public const MONTHLY_TARGET_CODES = [
        self::CODE_TARGET_MET,
        self::CODE_TARGET_OVERACHIEVE_125,
    ];

    public function __construct(
        protected AgentAnalyticsService $analyticsService,
        protected PeriodBoundaryService $boundaryService
    ) {}

    /**
     * Check if an agent is eligible to receive achievements.
     * Criteria:
     * 1. Must be high_level_agent or low_level_agent.
     * 2. Must have active status.
     * 3. Must have verified KYC status.
     */
    public function isAgentEligible(User $agent): bool
    {
        if (!$agent->isHighLevelAgent() && !$agent->isLowLevelAgent()) {
            return false;
        }

        if ($agent->status !== UserStatus::ACTIVE) {
            return false;
        }

        $profile = $agent->agentProfile;
        if (!$profile || $profile->kyc_status !== 'verified') {
            return false;
        }

        return true;
    }

    /**
     * Authoritatively extract lifetime commercial metrics for an agent using FV-04-01 methods.
     * Respects Colombo timezone boundaries, exclusion of cancelled/uncollected orders,
     * and strict deduction of full returns and partial refunds.
     *
     * @return array{
     *     net_product_sales: string,
     *     total_commercial_orders: int,
     *     total_fulfilled_orders: int,
     *     total_returned_orders: int
     * }
     */
    public function getAgentLifetimeMetrics(User $agent): array
    {
        $allTime = $this->boundaryService->resolvePeriodUtcBoundaries('all_time');
        $summary = $this->analyticsService->computeAnalyticsSummary(
            agentIds: $agent->id,
            referralCodes: $agent->referral_code,
            startUtc: $allTime['startUtc'],
            endUtc: $allTime['endUtc']
        );

        return [
            'net_product_sales' => $summary['net_product_sales'],
            'total_commercial_orders' => (int) $summary['total_commercial_orders'],
            'total_fulfilled_orders' => (int) $summary['total_fulfilled_orders'],
            'total_returned_orders' => (int) $summary['total_returned_orders'],
        ];
    }

    /**
     * Determine whether an agent qualifies for a cumulative achievement definition.
     *
     * @param AgentAchievement $achievement
     * @param array $metrics
     * @return array{qualifies: bool, value: string}
     */
    public function checkCumulativeQualification(AgentAchievement $achievement, array $metrics): array
    {
        $thresholdStr = AgentAnalyticsService::normalizeDecimalString($achievement->threshold_value, false);

        switch ($achievement->code) {
            case self::CODE_FIRST_ORDER:
                // Qualifies if agent has at least 1 delivered/cash collected non-returned order and net commercial sales > 0
                $hasFulfilled = $metrics['total_fulfilled_orders'] >= 1;
                $hasPositiveSales = bccomp($metrics['net_product_sales'], '0.00', 2) > 0;
                $qualifies = $hasFulfilled && $hasPositiveSales;
                $value = (string) $metrics['total_fulfilled_orders'];
                break;

            case self::CODE_100K:
            case self::CODE_500K:
            case self::CODE_1M:
            default:
                $netSales = AgentAnalyticsService::normalizeDecimalString($metrics['net_product_sales'], true);
                $qualifies = bccomp($netSales, $thresholdStr, 2) >= 0;
                $value = $netSales;
                break;
        }

        return [
            'qualifies' => $qualifies,
            'value' => $value,
        ];
    }

    /**
     * Evaluate cumulative lifetime milestones for a single agent.
     * Handles:
     * - New award creation with lock and version linkage.
     * - Refund-induced correction (active -> corrected) when net metric drops below threshold.
     * - Re-qualification (corrected -> active) when subsequent sales raise metric back above threshold.
     * - Permanent exclusion of revoked awards (revoked awards are NEVER re-awarded or reinstated automatically).
     *
     * @return array{
     *     eligible: bool,
     *     evaluated: int,
     *     awarded: int,
     *     corrected: int,
     *     requalified: int,
     *     awards: Collection<int, AgentAchievementAward>
     * }
     */
    public function evaluateCumulativeMilestonesForAgent(
        User $agent,
        ?int $evaluationRunId = null,
        ?User $actor = null
    ): array {
        if (!$this->isAgentEligible($agent)) {
            return [
                'eligible' => false,
                'evaluated' => 0,
                'awarded' => 0,
                'corrected' => 0,
                'requalified' => 0,
                'awards' => collect(),
            ];
        }

        $metrics = $this->getAgentLifetimeMetrics($agent);
        $achievements = AgentAchievement::whereIn('code', self::CUMULATIVE_CODES)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $awardedCount = 0;
        $correctedCount = 0;
        $requalifiedCount = 0;
        $evaluatedCount = 0;
        $awardsResult = collect();

        DB::transaction(function () use (
            $agent,
            $metrics,
            $achievements,
            $evaluationRunId,
            $actor,
            &$awardedCount,
            &$correctedCount,
            &$requalifiedCount,
            &$evaluatedCount,
            &$awardsResult
        ) {
            foreach ($achievements as $achievement) {
                $evaluatedCount++;
                $qualification = $this->checkCumulativeQualification($achievement, $metrics);
                $qualifies = $qualification['qualifies'];
                $metricValue = $qualification['value'];

                // Query existing award with exclusive row lock
                $award = AgentAchievementAward::where('agent_id', $agent->id)
                    ->where('achievement_id', $achievement->id)
                    ->where('period_key', 'lifetime')
                    ->lockForUpdate()
                    ->first();

                // Case 1: No award exists yet
                if (!$award) {
                    if ($qualifies) {
                        $version = $this->resolveCurrentVersion($achievement);
                        try {
                            $newAward = AgentAchievementAward::create([
                                'agent_id' => $agent->id,
                                'achievement_id' => $achievement->id,
                                'version_id' => $version->id,
                                'period_key' => 'lifetime',
                                'achieved_value' => $metricValue,
                                'threshold_snapshot' => $achievement->threshold_value,
                                'status' => 'active',
                                'is_discretionary' => false,
                                'awarded_at' => now(),
                                'awarded_by' => $actor?->id,
                                'metadata' => [
                                    'evaluation_run_id' => $evaluationRunId,
                                    'qualifying_metric' => $achievement->metric_type,
                                    'initial_metric_value' => $metricValue,
                                ],
                            ]);

                            AuditLog::create([
                                'user_id' => $actor?->id,
                                'action' => 'agent_achievement_awarded',
                                'auditable_type' => AgentAchievementAward::class,
                                'auditable_id' => $newAward->id,
                                'new_values' => [
                                    'agent_id' => $agent->id,
                                    'achievement_code' => $achievement->code,
                                    'achieved_value' => $metricValue,
                                    'threshold' => (string) $achievement->threshold_value,
                                    'status' => 'active',
                                    'evaluation_run_id' => $evaluationRunId,
                                ],
                                'ip_address' => request()->ip() ?? '127.0.0.1',
                                'user_agent' => request()->userAgent() ?? 'CLI/System',
                            ]);

                            $awardedCount++;
                            $awardsResult->push($newAward);
                            $this->dispatchAwardNotification($newAward, 'earned');
                        } catch (QueryException $e) {
                            if ($this->isUniqueConstraintViolation($e)) {
                                // Concurrent process created award; reload and continue
                                $existing = AgentAchievementAward::where('agent_id', $agent->id)
                                    ->where('achievement_id', $achievement->id)
                                    ->where('period_key', 'lifetime')
                                    ->first();
                                if ($existing) {
                                    $awardsResult->push($existing);
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }
                    continue;
                }

                // Case 2: Award is revoked
                // Permanent automated protection: NEVER reinstate a revoked award
                if ($award->status === 'revoked') {
                    $awardsResult->push($award);
                    continue;
                }

                // Case 3: Award is currently active
                if ($award->status === 'active') {
                    if (!$qualifies) {
                        // Metric adjusted below threshold due to customer refund/return
                        $oldValues = [
                            'status' => $award->status,
                            'achieved_value' => (string) $award->achieved_value,
                        ];

                        $award->status = 'corrected';
                        $award->achieved_value = $metricValue;
                        $award->revocation_reason = "Net commercial sales adjusted below threshold ({$achievement->threshold_value}) due to customer refund/return.";
                        $award->metadata = array_merge($award->metadata ?? [], [
                            'corrected_at' => now()->toIso8601String(),
                            'corrected_metric_value' => $metricValue,
                            'evaluation_run_id' => $evaluationRunId,
                        ]);
                        $award->save();

                        AuditLog::create([
                            'user_id' => $actor?->id,
                            'action' => 'agent_achievement_award_corrected',
                            'auditable_type' => AgentAchievementAward::class,
                            'auditable_id' => $award->id,
                            'old_values' => $oldValues,
                            'new_values' => [
                                'status' => 'corrected',
                                'achieved_value' => $metricValue,
                                'reason' => $award->revocation_reason,
                                'evaluation_run_id' => $evaluationRunId,
                            ],
                            'ip_address' => request()->ip() ?? '127.0.0.1',
                            'user_agent' => request()->userAgent() ?? 'CLI/System',
                        ]);

                        $correctedCount++;
                        $this->dispatchAwardNotification($award, 'corrected');
                    } else {
                        // Update progress snapshot if value has progressed higher
                        if (bccomp($metricValue, (string) $award->achieved_value, 2) > 0) {
                            $award->achieved_value = $metricValue;
                            $award->save();
                        }
                    }
                    $awardsResult->push($award);
                    continue;
                }

                // Case 4: Award is currently corrected
                if ($award->status === 'corrected') {
                    if ($qualifies) {
                        // Subsequent commercial sales have raised metric back to or above threshold
                        $oldValues = [
                            'status' => $award->status,
                            'achieved_value' => (string) $award->achieved_value,
                        ];

                        $award->status = 'active';
                        $award->achieved_value = $metricValue;
                        $award->revocation_reason = null;
                        $award->metadata = array_merge($award->metadata ?? [], [
                            'requalified_at' => now()->toIso8601String(),
                            'requalified_metric_value' => $metricValue,
                            'evaluation_run_id' => $evaluationRunId,
                        ]);
                        $award->save();

                        AuditLog::create([
                            'user_id' => $actor?->id,
                            'action' => 'agent_achievement_award_requalified',
                            'auditable_type' => AgentAchievementAward::class,
                            'auditable_id' => $award->id,
                            'old_values' => $oldValues,
                            'new_values' => [
                                'status' => 'active',
                                'achieved_value' => $metricValue,
                                'evaluation_run_id' => $evaluationRunId,
                            ],
                            'ip_address' => request()->ip() ?? '127.0.0.1',
                            'user_agent' => request()->userAgent() ?? 'CLI/System',
                        ]);

                        $requalifiedCount++;
                        $this->dispatchAwardNotification($award, 'requalified');
                    }
                    $awardsResult->push($award);
                }
            }
        });

        return [
            'eligible' => true,
            'evaluated' => $evaluatedCount,
            'awarded' => $awardedCount,
            'corrected' => $correctedCount,
            'requalified' => $requalifiedCount,
            'awards' => $awardsResult,
        ];
    }

    /**
     * Phase 1 of Two-Phase Backfill Governance:
     * Generates a dry-run preview snapshot of eligible agents and prospective cumulative milestone awards.
     * Computes an immutable SHA256 checksum and creates a run record with 'pending_owner_authorization'.
     * Zero awards are written to the database during preview.
     */
    public function previewHistoricalBackfill(?User $actor = null): AgentAchievementEvaluationRun
    {
        $eligibleAgents = User::query()
            ->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT, 'high_level_agent', 'low_level_agent'])
            ->where('status', UserStatus::ACTIVE)
            ->whereHas('agentProfile', function ($q) {
                $q->where('kyc_status', 'verified');
            })
            ->orderBy('id')
            ->get();

        $achievements = AgentAchievement::whereIn('code', self::CUMULATIVE_CODES)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $previewAwards = [];

        foreach ($eligibleAgents as $agent) {
            $metrics = $this->getAgentLifetimeMetrics($agent);

            foreach ($achievements as $achievement) {
                $qualification = $this->checkCumulativeQualification($achievement, $metrics);
                if (!$qualification['qualifies']) {
                    continue;
                }

                $existingAward = AgentAchievementAward::where('agent_id', $agent->id)
                    ->where('achievement_id', $achievement->id)
                    ->where('period_key', 'lifetime')
                    ->first();

                // Prospective award if not already awarded or currently corrected
                if (!$existingAward || $existingAward->status === 'corrected') {
                    $previewAwards[] = [
                        'agent_id' => $agent->id,
                        'agent_name' => $agent->name,
                        'agent_email' => $agent->email,
                        'achievement_id' => $achievement->id,
                        'achievement_code' => $achievement->code,
                        'achievement_name' => $achievement->name,
                        'badge_tier' => $achievement->badge_tier,
                        'threshold_snapshot' => (string) $achievement->threshold_value,
                        'metric_value' => $qualification['value'],
                        'current_award_status' => $existingAward ? $existingAward->status : 'none',
                        'prospective_action' => $existingAward ? 'requalify' : 'new_award',
                    ];
                }
            }
        }

        $checksum = hash('sha256', json_encode($previewAwards));

        $run = AgentAchievementEvaluationRun::create([
            'run_type' => 'historical_backfill',
            'period_type' => 'lifetime',
            'period_key' => 'lifetime',
            'started_at' => now(),
            'status' => 'pending_owner_authorization',
            'agents_evaluated' => $eligibleAgents->count(),
            'awards_created' => count($previewAwards),
            'streaks_updated' => 0,
            'metadata' => [
                'preview_awards' => $previewAwards,
                'checksum' => $checksum,
                'staged_at' => now()->toIso8601String(),
                'expires_at' => now()->addHours(24)->toIso8601String(),
                'last_processed_agent_id' => 0,
            ],
            'executed_by' => $actor?->id,
        ]);

        return $run;
    }

    /**
     * Phase 2 of Two-Phase Backfill Governance:
     * Exclusively executed by the authenticated Business Owner.
     * Verifies owner business authority, checks expiration window, validates checksum integrity,
     * transitions status to 'authorized', and creates an immutable AuditLog entry.
     *
     * @throws AuthorizationException If actor is not the Business Owner
     * @throws DomainException If run is not in pending status, expired, or tampered
     */
    public function authorizeBackfillRun(AgentAchievementEvaluationRun $run, User $owner): AgentAchievementEvaluationRun
    {
        if (!$owner->isBusinessAuthority()) {
            throw new AuthorizationException(
                "Unauthorized: Only the Business Owner has authority to authorize achievement backfill runs."
            );
        }

        if ($run->status !== 'pending_owner_authorization') {
            throw new DomainException(
                "Invalid State: Run #{$run->id} cannot be authorized because its status is '{$run->status}'."
            );
        }

        $metadata = $run->metadata ?? [];
        $expiresAtStr = $metadata['expires_at'] ?? null;
        if ($expiresAtStr && now()->greaterThan(Carbon::parse($expiresAtStr))) {
            $run->update([
                'status' => 'failed',
                'error_summary' => 'Authorization window expired (24 hours exceeded). Re-preview required.',
            ]);
            throw new DomainException("Authorization Error: Preview snapshot has expired. Generate a new preview.");
        }

        // Verify cryptographic checksum integrity of the preview snapshot
        $storedChecksum = $metadata['checksum'] ?? '';
        $computedChecksum = hash('sha256', json_encode($metadata['preview_awards'] ?? []));
        if (!hash_equals($storedChecksum, $computedChecksum)) {
            $run->update([
                'status' => 'failed',
                'error_summary' => 'Preview snapshot checksum tampering detected.',
            ]);
            throw new DomainException("Security Violation: Preview snapshot checksum mismatch. Backfill aborted.");
        }

        $run->update([
            'status' => 'authorized',
            'metadata' => array_merge($metadata, [
                'authorized_by' => $owner->id,
                'authorized_at' => now()->toIso8601String(),
            ]),
        ]);

        AuditLog::create([
            'user_id' => $owner->id,
            'action' => 'agent_achievement_backfill_authorized',
            'auditable_type' => AgentAchievementEvaluationRun::class,
            'auditable_id' => $run->id,
            'new_values' => [
                'run_id' => $run->id,
                'authorized_by' => $owner->id,
                'agents_evaluated' => $run->agents_evaluated,
                'prospective_awards' => $run->awards_created,
                'checksum' => $storedChecksum,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return $run;
    }

    /**
     * Phase 3 of Two-Phase Backfill Governance:
     * Executes an authorized backfill run in bounded batches of $batchSize agents (default 100).
     * Re-validates eligibility and financial metrics before creating awards.
     * Supports safe interruption and resumption from last_processed_agent_id.
     * Prevents overlapping concurrent execution.
     *
     * @throws AuthorizationException If run is not authorized
     * @throws DomainException If another run is already executing
     */
    public function executeBackfillRun(
        AgentAchievementEvaluationRun $run,
        ?User $actor = null,
        int $batchSize = 100
    ): array {
        if ($run->status !== 'authorized') {
            throw new AuthorizationException(
                "Unauthorized: Backfill run #{$run->id} is not authorized for execution (status: '{$run->status}')."
            );
        }

        // Verify that an immutable authorization audit log exists
        $hasAuthLog = AuditLog::where('action', 'agent_achievement_backfill_authorized')
            ->where('auditable_type', AgentAchievementEvaluationRun::class)
            ->where('auditable_id', $run->id)
            ->exists();

        if (!$hasAuthLog) {
            throw new AuthorizationException(
                "Security Violation: Run #{$run->id} lacks an immutable Owner authorization audit log."
            );
        }

        // Concurrency Guard: Ensure no other backfill run is actively executing
        $activeOtherRun = AgentAchievementEvaluationRun::where('run_type', 'historical_backfill')
            ->where('status', 'running')
            ->where('id', '!=', $run->id)
            ->exists();

        if ($activeOtherRun) {
            throw new DomainException("Concurrency Conflict: Another backfill run is currently executing.");
        }

        $run->update(['status' => 'running']);

        $metadata = $run->metadata ?? [];
        $lastProcessedId = (int) ($metadata['last_processed_agent_id'] ?? 0);
        $totalAwardsCreated = (int) ($metadata['actual_awards_created'] ?? 0);
        $totalAwardsCorrected = (int) ($metadata['actual_awards_corrected'] ?? 0);
        $totalAwardsRequalified = (int) ($metadata['actual_awards_requalified'] ?? 0);
        $totalAgentsProcessed = (int) ($metadata['actual_agents_processed'] ?? 0);

        while (true) {
            $agentBatch = User::query()
                ->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT, 'high_level_agent', 'low_level_agent'])
                ->where('status', UserStatus::ACTIVE)
                ->whereHas('agentProfile', function ($q) {
                    $q->where('kyc_status', 'verified');
                })
                ->where('id', '>', $lastProcessedId)
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($agentBatch->isEmpty()) {
                break;
            }

            foreach ($agentBatch as $agent) {
                $eval = $this->evaluateCumulativeMilestonesForAgent($agent, $run->id, $actor);
                $totalAwardsCreated += $eval['awarded'];
                $totalAwardsCorrected += $eval['corrected'];
                $totalAwardsRequalified += $eval['requalified'];
                $totalAgentsProcessed++;
                $lastProcessedId = $agent->id;
            }

            // Update checkpoint after batch
            $run->update([
                'agents_evaluated' => $totalAgentsProcessed,
                'awards_created' => $totalAwardsCreated,
                'metadata' => array_merge($run->metadata ?? [], [
                    'last_processed_agent_id' => $lastProcessedId,
                    'actual_awards_created' => $totalAwardsCreated,
                    'actual_awards_corrected' => $totalAwardsCorrected,
                    'actual_awards_requalified' => $totalAwardsRequalified,
                    'actual_agents_processed' => $totalAgentsProcessed,
                ]),
            ]);
        }

        $run->update([
            'status' => 'completed',
            'completed_at' => now(),
            'agents_evaluated' => $totalAgentsProcessed,
            'awards_created' => $totalAwardsCreated,
            'metadata' => array_merge($run->metadata ?? [], [
                'completed_at' => now()->toIso8601String(),
                'actual_awards_created' => $totalAwardsCreated,
                'actual_awards_corrected' => $totalAwardsCorrected,
                'actual_awards_requalified' => $totalAwardsRequalified,
                'actual_agents_processed' => $totalAgentsProcessed,
            ]),
        ]);

        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => 'agent_achievement_backfill_completed',
            'auditable_type' => AgentAchievementEvaluationRun::class,
            'auditable_id' => $run->id,
            'new_values' => [
                'run_id' => $run->id,
                'agents_evaluated' => $totalAgentsProcessed,
                'awards_created' => $totalAwardsCreated,
                'awards_corrected' => $totalAwardsCorrected,
                'awards_requalified' => $totalAwardsRequalified,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return [
            'run_id' => $run->id,
            'status' => 'completed',
            'agents_evaluated' => $totalAgentsProcessed,
            'awards_created' => $totalAwardsCreated,
            'awards_corrected' => $totalAwardsCorrected,
            'awards_requalified' => $totalAwardsRequalified,
        ];
    }

    /**
     * Safe post-commit notification dispatch for achievement awards.
     * Uses DB::afterCommit() to guarantee dispatch only occurs after the database transaction commits.
     * Dispatches safely without breaking or rolling back the award transaction if notification fails.
     * Guarantees zero notifications for revoked awards.
     */
    public function dispatchAwardNotification(AgentAchievementAward $award, string $event = 'earned', ?string $customMessage = null): void
    {
        // Zero notifications for revoked awards
        if ($award->status === 'revoked') {
            return;
        }

        DB::afterCommit(function () use ($award, $event, $customMessage) {
            try {
                $agent = $award->agent ?? User::find($award->agent_id);
                if (!$agent) {
                    return;
                }

                // Check if award was revoked in the meantime
                $freshAward = AgentAchievementAward::with('achievement')->find($award->id);
                if (!$freshAward || $freshAward->status === 'revoked') {
                    return;
                }

                $notification = new AgentAchievementAwardedNotification($freshAward, $event, $customMessage);

                // Deterministic idempotency: check if notification with this deterministic id already exists
                $exists = DB::table('notifications')->where('id', $notification->id)->exists();
                if ($exists) {
                    return;
                }

                try {
                    $agent->notify($notification);
                } catch (\Throwable $notifyError) {
                    if ($notifyError instanceof QueryException && $this->isUniqueConstraintViolation($notifyError)) {
                        // Concurrent duplicate delivery caught by database primary key uniqueness
                        return;
                    }
                    throw $notifyError;
                }
            } catch (\Throwable $e) {
                Log::warning(
                    "Achievement notification dispatch failed for award #{$award->id} (event: {$event}): " . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        });
    }

    /**
     * Audit-driven failure recovery engine for idempotent agent achievement notifications.
     * Scans immutable audit logs, reconstructs deterministic UUIDv5 primary keys,
     * and safely delivers missing database notifications.
     *
     * @param int|null $hours
     * @return array{
     *     scanned: int,
     *     existing: int,
     *     repaired: int,
     *     skipped_revoked: int,
     *     skipped_inactive: int,
     *     missing_awards: int
     * }
     */
    public function retryFailedNotifications(?int $hours = null): array
    {
        $actions = [
            'agent_achievement_awarded',
            'agent_achievement_award_corrected',
            'agent_achievement_award_requalified',
        ];

        $query = AuditLog::query()
            ->where('auditable_type', AgentAchievementAward::class)
            ->whereIn('action', $actions)
            ->orderBy('id', 'asc');

        if ($hours !== null && $hours > 0) {
            $query->where('created_at', '>=', Carbon::now()->subHours($hours));
        }

        $scanned = 0;
        $existing = 0;
        $repaired = 0;
        $skippedRevoked = 0;
        $skippedInactive = 0;
        $missingAwards = 0;

        $query->chunk(100, function ($logs) use (
            &$scanned,
            &$existing,
            &$repaired,
            &$skippedRevoked,
            &$skippedInactive,
            &$missingAwards
        ) {
            foreach ($logs as $log) {
                $scanned++;

                /** @var AgentAchievementAward|null $award */
                $award = AgentAchievementAward::with('achievement')->find($log->auditable_id);
                if (!$award) {
                    $missingAwards++;
                    continue;
                }

                // Strictly skip revoked awards
                if ($award->status === 'revoked') {
                    $skippedRevoked++;
                    continue;
                }

                // Determine event
                $event = match ($log->action) {
                    'agent_achievement_award_corrected' => 'corrected',
                    'agent_achievement_award_requalified' => 'requalified',
                    default => match ($award->achievement?->code) {
                        self::CODE_TARGET_STREAK_3M => 'streak',
                        self::CODE_TARGET_MET, self::CODE_TARGET_OVERACHIEVE_125 => 'monthly_target',
                        default => 'earned',
                    },
                };

                $deterministicId = Uuid::uuid5(
                    Uuid::NAMESPACE_DNS,
                    "auraluxe.achievement.award.{$award->id}.{$event}"
                )->toString();

                if (DB::table('notifications')->where('id', $deterministicId)->exists()) {
                    $existing++;
                    continue;
                }

                $recipient = User::find($award->agent_id);
                if (!$recipient || !$this->isAgentEligible($recipient)) {
                    $skippedInactive++;
                    continue;
                }

                try {
                    $notification = new AgentAchievementAwardedNotification($award, $event);
                    $recipient->notify($notification);
                    $repaired++;
                } catch (\Throwable $e) {
                    if ($e instanceof QueryException && $this->isUniqueConstraintViolation($e)) {
                        $existing++;
                    } else {
                        Log::warning("Retry notification failed for award #{$award->id}: " . $e->getMessage());
                    }
                }
            }
        });

        return [
            'scanned' => $scanned,
            'existing' => $existing,
            'repaired' => $repaired,
            'skipped_revoked' => $skippedRevoked,
            'skipped_inactive' => $skippedInactive,
            'missing_awards' => $missingAwards,
        ];
    }

    /**
     * Authoritative historical streak reconciliation engine.
     * Computes consecutive Colombo calendar month progression across all historical target awards.
     * Guaranteed to be independent of job execution order or out-of-order evaluations.
     *
     * @param User $agent
     * @param int|null $evaluationRunId
     * @param User|null $actor
     * @param string|null $currentEvaluatingMonth
     * @param bool|null $currentMonthTargetMet
     * @return AgentAchievementStreak
     */
    public function reconcileTargetAttainmentStreak(
        User $agent,
        ?int $evaluationRunId = null,
        ?User $actor = null,
        ?string $currentEvaluatingMonth = null,
        ?bool $currentMonthTargetMet = null
    ): AgentAchievementStreak {
        $streak = AgentAchievementStreak::where('agent_id', $agent->id)
            ->where('streak_type', 'target_achievement')
            ->lockForUpdate()
            ->first();

        if (!$streak) {
            try {
                $streak = AgentAchievementStreak::create([
                    'agent_id' => $agent->id,
                    'streak_type' => 'target_achievement',
                    'current_streak' => 0,
                    'longest_streak' => 0,
                    'last_qualifying_period' => null,
                    'streak_started_period' => null,
                    'is_active' => true,
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    $streak = AgentAchievementStreak::where('agent_id', $agent->id)
                        ->where('streak_type', 'target_achievement')
                        ->lockForUpdate()
                        ->first();
                } else {
                    throw $e;
                }
            }
        }

        $targetAchievement = AgentAchievement::where('code', self::CODE_TARGET_MET)->first();
        if (!$targetAchievement) {
            return $streak;
        }

        // Fetch all active target met awards sorted chronologically
        $activeAwards = AgentAchievementAward::where('agent_id', $agent->id)
            ->where('achievement_id', $targetAchievement->id)
            ->where('status', 'active')
            ->orderBy('period_key', 'asc')
            ->get();

        if ($activeAwards->isEmpty()) {
            $streak->update([
                'current_streak' => 0,
                'streak_started_period' => null,
                'last_qualifying_period' => null,
            ]);
            return $streak;
        }

        $longestStreak = 0;
        $runningStreak = 0;
        $streakStarted = null;
        $lastPeriod = null;
        $monthStreaks = [];

        foreach ($activeAwards as $award) {
            $m = $award->period_key;
            $mCarbon = Carbon::createFromFormat('Y-m', $m, PeriodBoundaryService::TIMEZONE);
            $expectedPrevious = $mCarbon->copy()->subMonthNoOverflow()->format('Y-m');

            if ($lastPeriod !== null && $lastPeriod === $expectedPrevious) {
                // Strictly consecutive Colombo calendar month
                $runningStreak++;
            } else {
                // Gap month, missing month, or initial month
                $runningStreak = 1;
                $streakStarted = $m;
            }

            $lastPeriod = $m;
            $monthStreaks[$m] = [
                'streak' => $runningStreak,
                'started' => $streakStarted,
            ];

            if ($runningStreak > $longestStreak) {
                $longestStreak = $runningStreak;
            }

            // Evaluate streak award for this month at 3-month milestones (3, 6, 9...)
            if ($runningStreak >= 3 && ($runningStreak % 3 === 0)) {
                $this->ensureStreakAwardExists($agent, $m, $runningStreak, $evaluationRunId, $actor);
            }
        }

        // Audit streak awards: if an award exists for a month where streak is no longer a multiple of 3 (e.g. broken by refund), correct it
        $streakAchievement = AgentAchievement::where('code', self::CODE_TARGET_STREAK_3M)->first();
        if ($streakAchievement) {
            $existingStreakAwards = AgentAchievementAward::where('agent_id', $agent->id)
                ->where('achievement_id', $streakAchievement->id)
                ->where('status', 'active')
                ->get();

            foreach ($existingStreakAwards as $stAward) {
                $stMonth = $stAward->period_key;
                $monthInfo = $monthStreaks[$stMonth] ?? null;
                $validStreak = $monthInfo ? $monthInfo['streak'] : 0;

                if ($validStreak < 3 || ($validStreak % 3 !== 0)) {
                    $stAward->status = 'corrected';
                    $stAward->achieved_value = (string) $validStreak;
                    $stAward->revocation_reason = "Consecutive achievement streak interrupted or sales adjusted below target.";
                    $stAward->save();

                    AuditLog::create([
                        'user_id' => $actor?->id,
                        'action' => 'agent_achievement_award_corrected',
                        'auditable_type' => AgentAchievementAward::class,
                        'auditable_id' => $stAward->id,
                        'new_values' => [
                            'status' => 'corrected',
                            'achieved_value' => (string) $validStreak,
                            'reason' => $stAward->revocation_reason,
                            'evaluation_run_id' => $evaluationRunId,
                        ],
                        'ip_address' => request()->ip() ?? '127.0.0.1',
                        'user_agent' => request()->userAgent() ?? 'CLI/System',
                    ]);

                    $this->dispatchAwardNotification($stAward, 'corrected');
                }
            }
        }

        // Determine current_streak for the agent
        // If currentEvaluatingMonth was evaluated as NOT met, and it is on or after the last active month:
        if ($currentEvaluatingMonth !== null && $currentMonthTargetMet === false && ($lastPeriod === null || $currentEvaluatingMonth >= $lastPeriod)) {
            $streak->current_streak = 0;
            $streak->streak_started_period = null;
        } else {
            $streak->current_streak = $runningStreak;
            $streak->streak_started_period = $streakStarted;
            $streak->last_qualifying_period = $lastPeriod;
        }

        $streak->longest_streak = max($streak->longest_streak, $longestStreak);
        $streak->save();

        return $streak;
    }

    /**
     * Ensure a 3-month milestone streak award exists for a qualifying month.
     */
    protected function ensureStreakAwardExists(
        User $agent,
        string $month,
        int $streakMonths,
        ?int $evaluationRunId = null,
        ?User $actor = null
    ): ?AgentAchievementAward {
        $achievement = AgentAchievement::where('code', self::CODE_TARGET_STREAK_3M)
            ->where('is_active', true)
            ->first();

        if (!$achievement) {
            return null;
        }

        $award = AgentAchievementAward::where('agent_id', $agent->id)
            ->where('achievement_id', $achievement->id)
            ->where('period_key', $month)
            ->lockForUpdate()
            ->first();

        if (!$award) {
            $version = $this->resolveCurrentVersion($achievement);
            try {
                $newAward = AgentAchievementAward::create([
                    'agent_id' => $agent->id,
                    'achievement_id' => $achievement->id,
                    'version_id' => $version->id,
                    'period_key' => $month,
                    'achieved_value' => (string) $streakMonths,
                    'threshold_snapshot' => $achievement->threshold_value,
                    'status' => 'active',
                    'is_discretionary' => false,
                    'awarded_at' => now(),
                    'awarded_by' => $actor?->id,
                    'metadata' => [
                        'evaluation_run_id' => $evaluationRunId,
                        'qualifying_metric' => 'streak_months',
                        'current_streak' => $streakMonths,
                        'period_month' => $month,
                    ],
                ]);

                AuditLog::create([
                    'user_id' => $actor?->id,
                    'action' => 'agent_achievement_awarded',
                    'auditable_type' => AgentAchievementAward::class,
                    'auditable_id' => $newAward->id,
                    'new_values' => [
                        'agent_id' => $agent->id,
                        'achievement_code' => $achievement->code,
                        'achieved_value' => (string) $streakMonths,
                        'period_key' => $month,
                        'threshold' => (string) $achievement->threshold_value,
                        'status' => 'active',
                        'evaluation_run_id' => $evaluationRunId,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent() ?? 'CLI/System',
                ]);

                $this->dispatchAwardNotification($newAward, 'streak');
                return $newAward;
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    return AgentAchievementAward::where('agent_id', $agent->id)
                        ->where('achievement_id', $achievement->id)
                        ->where('period_key', $month)
                        ->first();
                }
                throw $e;
            }
        }

        if ($award->status === 'corrected') {
            $award->status = 'active';
            $award->achieved_value = (string) $streakMonths;
            $award->revocation_reason = null;
            $award->save();

            AuditLog::create([
                'user_id' => $actor?->id,
                'action' => 'agent_achievement_award_requalified',
                'auditable_type' => AgentAchievementAward::class,
                'auditable_id' => $award->id,
                'new_values' => [
                    'status' => 'active',
                    'achieved_value' => (string) $streakMonths,
                    'evaluation_run_id' => $evaluationRunId,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            $this->dispatchAwardNotification($award, 'requalified');
        }

        return $award;
    }

    /**
     * Update consecutive streak tracker for an agent following a monthly target evaluation.
     * Concurrency-safe with row locking and unique-key conflict recovery.
     *
     * @param User $agent
     * @param string $month YYYY-MM
     * @param bool $targetMet
     * @param int|null $evaluationRunId
     * @param User|null $actor
     * @return AgentAchievementStreak
     */
    public function updateTargetAttainmentStreak(
        User $agent,
        string $month,
        bool $targetMet,
        ?int $evaluationRunId = null,
        ?User $actor = null
    ): AgentAchievementStreak {
        return $this->reconcileTargetAttainmentStreak(
            $agent,
            $evaluationRunId,
            $actor,
            $month,
            $targetMet
        );
    }

    /**
     * Evaluate and manage the 3-Month Consecutive Target Achievement Streak award (TARGET_STREAK_3M).
     * Awards at each 3-month cycle completion (3, 6, 9...).
     * Manages correction and requalification lifecycle.
     */
    protected function evaluateStreakAward(
        User $agent,
        AgentAchievementStreak $streak,
        string $month,
        ?int $evaluationRunId = null,
        ?User $actor = null
    ): ?AgentAchievementAward {
        $achievement = AgentAchievement::where('code', self::CODE_TARGET_STREAK_3M)
            ->where('is_active', true)
            ->first();

        if (!$achievement) {
            return null;
        }

        $award = AgentAchievementAward::where('agent_id', $agent->id)
            ->where('achievement_id', $achievement->id)
            ->where('period_key', $month)
            ->first();

        return $award;
    }

    /**
     * Evaluate monthly target achievements (TARGET_MET_MONTHLY, TARGET_OVERACHIEVE_125)
     * and consecutive streaks (TARGET_STREAK_3M) for a single agent.
     *
     * @param User $agent
     * @param string $month Format YYYY-MM
     * @param int|null $evaluationRunId
     * @param User|null $actor
     * @return array{
     *     eligible: bool,
     *     has_target: bool,
     *     target_amount: ?string,
     *     net_sales: string,
     *     attainment_pct: ?string,
     *     evaluated: int,
     *     awarded: int,
     *     corrected: int,
     *     requalified: int,
     *     streak_months: int,
     *     awards: Collection<int, AgentAchievementAward>
     * }
     */
    public function evaluateMonthlyTargetAchievementsForAgent(
        User $agent,
        string $month,
        ?int $evaluationRunId = null,
        ?User $actor = null
    ): array {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException("Invalid month format: '{$month}'. Expected YYYY-MM.");
        }

        if (!$this->isAgentEligible($agent)) {
            return [
                'eligible' => false,
                'has_target' => false,
                'target_amount' => null,
                'net_sales' => '0.00',
                'attainment_pct' => null,
                'evaluated' => 0,
                'awarded' => 0,
                'corrected' => 0,
                'requalified' => 0,
                'streak_months' => 0,
                'awards' => collect(),
            ];
        }

        // Look up authoritative sales target
        $targetRecord = AgentSalesTarget::where('agent_id', $agent->id)
            ->where('target_month', $month)
            ->first();

        // If target is missing or <= 0.00, target cannot be met
        $targetAmount = $targetRecord ? (string) $targetRecord->sales_target : null;
        $hasPositiveTarget = $targetAmount !== null && bccomp($targetAmount, '0.00', 2) > 0;

        // Resolve Colombo calendar month boundaries and convert to UTC
        $startColombo = Carbon::createFromFormat('Y-m', $month, PeriodBoundaryService::TIMEZONE)->startOfMonth();
        $endColombo = $startColombo->copy()->addMonthNoOverflow()->startOfMonth();
        $startUtc = $startColombo->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
        $endUtc = $endColombo->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');

        // Extract net commercial sales via authoritative AgentAnalyticsService
        $summary = $this->analyticsService->computeAnalyticsSummary(
            agentIds: $agent->id,
            referralCodes: $agent->referral_code,
            startUtc: $startUtc,
            endUtc: $endUtc,
            salesTargetAmount: $hasPositiveTarget ? $targetAmount : null
        );

        $netSales = $summary['net_product_sales'];
        $attainmentPct = $hasPositiveTarget
            ? bcmul(bcdiv($netSales, $targetAmount, 4), '100', 2)
            : null;

        $targetMet = $hasPositiveTarget && bccomp($attainmentPct, '100.00', 2) >= 0;

        $achievements = AgentAchievement::whereIn('code', self::MONTHLY_TARGET_CODES)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $awardedCount = 0;
        $correctedCount = 0;
        $requalifiedCount = 0;
        $evaluatedCount = 0;
        $awardsResult = collect();
        $streakRecord = null;

        DB::transaction(function () use (
            $agent,
            $month,
            $hasPositiveTarget,
            $targetAmount,
            $attainmentPct,
            $achievements,
            $targetMet,
            $evaluationRunId,
            $actor,
            &$awardedCount,
            &$correctedCount,
            &$requalifiedCount,
            &$evaluatedCount,
            &$awardsResult,
            &$streakRecord
        ) {
            foreach ($achievements as $achievement) {
                $evaluatedCount++;
                $thresholdStr = AgentAnalyticsService::normalizeDecimalString($achievement->threshold_value, false);
                $qualifies = $hasPositiveTarget && bccomp($attainmentPct, $thresholdStr, 2) >= 0;

                // Query existing award with row lock
                $award = AgentAchievementAward::where('agent_id', $agent->id)
                    ->where('achievement_id', $achievement->id)
                    ->where('period_key', $month)
                    ->lockForUpdate()
                    ->first();

                // Case 1: No award exists yet
                if (!$award) {
                    if ($qualifies) {
                        $version = $this->resolveCurrentVersion($achievement);
                        try {
                            $newAward = AgentAchievementAward::create([
                                'agent_id' => $agent->id,
                                'achievement_id' => $achievement->id,
                                'version_id' => $version->id,
                                'period_key' => $month,
                                'achieved_value' => $attainmentPct,
                                'threshold_snapshot' => $achievement->threshold_value,
                                'status' => 'active',
                                'is_discretionary' => false,
                                'awarded_at' => now(),
                                'awarded_by' => $actor?->id,
                                'metadata' => [
                                    'evaluation_run_id' => $evaluationRunId,
                                    'target_amount' => $targetAmount,
                                    'net_sales' => $attainmentPct,
                                    'period_month' => $month,
                                ],
                            ]);

                            AuditLog::create([
                                'user_id' => $actor?->id,
                                'action' => 'agent_achievement_awarded',
                                'auditable_type' => AgentAchievementAward::class,
                                'auditable_id' => $newAward->id,
                                'new_values' => [
                                    'agent_id' => $agent->id,
                                    'achievement_code' => $achievement->code,
                                    'achieved_value' => $attainmentPct,
                                    'period_key' => $month,
                                    'threshold' => (string) $achievement->threshold_value,
                                    'status' => 'active',
                                    'evaluation_run_id' => $evaluationRunId,
                                ],
                                'ip_address' => request()->ip() ?? '127.0.0.1',
                                'user_agent' => request()->userAgent() ?? 'CLI/System',
                            ]);

                            $awardedCount++;
                            $awardsResult->push($newAward);
                            $this->dispatchAwardNotification($newAward, 'monthly_target');
                        } catch (QueryException $e) {
                            if ($this->isUniqueConstraintViolation($e)) {
                                $existing = AgentAchievementAward::where('agent_id', $agent->id)
                                    ->where('achievement_id', $achievement->id)
                                    ->where('period_key', $month)
                                    ->first();
                                if ($existing) {
                                    $awardsResult->push($existing);
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }
                    continue;
                }

                // Case 2: Revoked - permanent automated protection
                if ($award->status === 'revoked') {
                    $awardsResult->push($award);
                    continue;
                }

                // Case 3: Active award
                if ($award->status === 'active') {
                    if (!$qualifies) {
                        $oldValues = [
                            'status' => $award->status,
                            'achieved_value' => (string) $award->achieved_value,
                        ];

                        $award->status = 'corrected';
                        $award->achieved_value = $attainmentPct ?? '0.00';
                        $award->revocation_reason = "Monthly target attainment adjusted below threshold ({$achievement->threshold_value}%) due to refunds or target adjustments.";
                        $award->metadata = array_merge($award->metadata ?? [], [
                            'corrected_at' => now()->toIso8601String(),
                            'evaluation_run_id' => $evaluationRunId,
                        ]);
                        $award->save();

                        AuditLog::create([
                            'user_id' => $actor?->id,
                            'action' => 'agent_achievement_award_corrected',
                            'auditable_type' => AgentAchievementAward::class,
                            'auditable_id' => $award->id,
                            'old_values' => $oldValues,
                            'new_values' => [
                                'status' => 'corrected',
                                'achieved_value' => $award->achieved_value,
                                'period_key' => $month,
                                'reason' => $award->revocation_reason,
                                'evaluation_run_id' => $evaluationRunId,
                            ],
                            'ip_address' => request()->ip() ?? '127.0.0.1',
                            'user_agent' => request()->userAgent() ?? 'CLI/System',
                        ]);

                        $correctedCount++;
                        $this->dispatchAwardNotification($award, 'corrected');
                    } else {
                        // Update progress snapshot if attainment increased
                        if ($attainmentPct !== null && bccomp($attainmentPct, (string) $award->achieved_value, 2) > 0) {
                            $award->achieved_value = $attainmentPct;
                            $award->save();
                        }
                    }
                    $awardsResult->push($award);
                    continue;
                }

                // Case 4: Corrected award
                if ($award->status === 'corrected') {
                    if ($qualifies) {
                        $oldValues = [
                            'status' => $award->status,
                            'achieved_value' => (string) $award->achieved_value,
                        ];

                        $award->status = 'active';
                        $award->achieved_value = $attainmentPct;
                        $award->revocation_reason = null;
                        $award->metadata = array_merge($award->metadata ?? [], [
                            'requalified_at' => now()->toIso8601String(),
                            'evaluation_run_id' => $evaluationRunId,
                        ]);
                        $award->save();

                        AuditLog::create([
                            'user_id' => $actor?->id,
                            'action' => 'agent_achievement_award_requalified',
                            'auditable_type' => AgentAchievementAward::class,
                            'auditable_id' => $award->id,
                            'old_values' => $oldValues,
                            'new_values' => [
                                'status' => 'active',
                                'achieved_value' => $attainmentPct,
                                'period_key' => $month,
                                'evaluation_run_id' => $evaluationRunId,
                            ],
                            'ip_address' => request()->ip() ?? '127.0.0.1',
                            'user_agent' => request()->userAgent() ?? 'CLI/System',
                        ]);

                        $requalifiedCount++;
                        $this->dispatchAwardNotification($award, 'requalified');
                    }
                    $awardsResult->push($award);
                }
            }

            // Streak tracking & Streak award evaluation
            $streakRecord = $this->updateTargetAttainmentStreak($agent, $month, $targetMet, $evaluationRunId, $actor);
            $streakAward = $this->evaluateStreakAward($agent, $streakRecord, $month, $evaluationRunId, $actor);
            if ($streakAward) {
                $awardsResult->push($streakAward);
            }
        });

        return [
            'eligible' => true,
            'has_target' => $hasPositiveTarget,
            'target_amount' => $targetAmount,
            'net_sales' => $netSales,
            'attainment_pct' => $attainmentPct,
            'evaluated' => $evaluatedCount,
            'awarded' => $awardedCount,
            'corrected' => $correctedCount,
            'requalified' => $requalifiedCount,
            'streak_months' => $streakRecord?->current_streak ?? 0,
            'awards' => $awardsResult,
        ];
    }

    /**
     * Batch evaluate monthly sales target achievements and streaks across all eligible active agents.
     * Enforces bounded batch processing with resumption support.
     *
     * @param string $month YYYY-MM
     * @param User|null $actor
     * @param int $batchSize
     * @return array{
     *     run_id: int,
     *     status: string,
     *     agents_evaluated: int,
     *     awards_created: int,
     *     awards_corrected: int,
     *     awards_requalified: int,
     *     streaks_updated: int
     * }
     */
    public function evaluateMonthlyTargetAchievementsBatch(
        string $month,
        ?User $actor = null,
        int $batchSize = 100
    ): array {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException("Invalid month format: '{$month}'. Expected YYYY-MM.");
        }

        $run = AgentAchievementEvaluationRun::create([
            'run_type' => 'monthly_targets',
            'period_type' => 'monthly',
            'period_key' => $month,
            'started_at' => now(),
            'status' => 'running',
            'agents_evaluated' => 0,
            'awards_created' => 0,
            'streaks_updated' => 0,
            'metadata' => [
                'target_month' => $month,
                'batch_size' => $batchSize,
                'last_processed_agent_id' => 0,
            ],
            'executed_by' => $actor?->id,
        ]);

        $lastProcessedId = 0;
        $totalEvaluated = 0;
        $totalAwarded = 0;
        $totalCorrected = 0;
        $totalRequalified = 0;
        $totalStreaksUpdated = 0;

        while (true) {
            $agents = User::query()
                ->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT, 'high_level_agent', 'low_level_agent'])
                ->where('status', UserStatus::ACTIVE)
                ->whereHas('agentProfile', function ($q) {
                    $q->where('kyc_status', 'verified');
                })
                ->where('id', '>', $lastProcessedId)
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($agents->isEmpty()) {
                break;
            }

            foreach ($agents as $agent) {
                $eval = $this->evaluateMonthlyTargetAchievementsForAgent($agent, $month, $run->id, $actor);
                $totalEvaluated++;
                $totalAwarded += $eval['awarded'];
                $totalCorrected += $eval['corrected'];
                $totalRequalified += $eval['requalified'];
                if ($eval['streak_months'] > 0) {
                    $totalStreaksUpdated++;
                }
                $lastProcessedId = $agent->id;
            }

            // Update checkpoint
            $run->update([
                'agents_evaluated' => $totalEvaluated,
                'awards_created' => $totalAwarded,
                'streaks_updated' => $totalStreaksUpdated,
                'metadata' => array_merge($run->metadata ?? [], [
                    'last_processed_agent_id' => $lastProcessedId,
                    'actual_awards_created' => $totalAwarded,
                    'actual_awards_corrected' => $totalCorrected,
                    'actual_awards_requalified' => $totalRequalified,
                ]),
            ]);
        }

        $run->update([
            'status' => 'completed',
            'completed_at' => now(),
            'agents_evaluated' => $totalEvaluated,
            'awards_created' => $totalAwarded,
            'streaks_updated' => $totalStreaksUpdated,
            'metadata' => array_merge($run->metadata ?? [], [
                'completed_at' => now()->toIso8601String(),
                'actual_awards_created' => $totalAwarded,
                'actual_awards_corrected' => $totalCorrected,
                'actual_awards_requalified' => $totalRequalified,
            ]),
        ]);

        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => 'agent_achievement_monthly_evaluation_completed',
            'auditable_type' => AgentAchievementEvaluationRun::class,
            'auditable_id' => $run->id,
            'new_values' => [
                'run_id' => $run->id,
                'target_month' => $month,
                'agents_evaluated' => $totalEvaluated,
                'awards_created' => $totalAwarded,
                'awards_corrected' => $totalCorrected,
                'awards_requalified' => $totalRequalified,
                'streaks_updated' => $totalStreaksUpdated,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return [
            'run_id' => $run->id,
            'status' => 'completed',
            'agents_evaluated' => $totalEvaluated,
            'awards_created' => $totalAwarded,
            'awards_corrected' => $totalCorrected,
            'awards_requalified' => $totalRequalified,
            'streaks_updated' => $totalStreaksUpdated,
        ];
    }

    /**
     * Alias matching Phase 1 architecture specification.
     */
    public function evaluateMonthlyTargetAchievements(string $month, ?User $actor = null): array
    {
        return $this->evaluateMonthlyTargetAchievementsBatch($month, $actor);
    }

    /**
     * Submit a discretionary achievement nomination.
     * Enforces eligibility, discretionary check, anti-self-nomination, duplicate pending protection,
     * and sets mandatory 30-day expiration.
     */
    public function submitDiscretionaryRequest(array $data, User $actor): AgentAchievementRequest
    {
        $agentId = (int) $data['agent_id'];
        $achievementId = (int) $data['achievement_id'];
        $periodKey = $data['period_key'] ?? 'lifetime';
        $justification = trim($data['justification'] ?? '');

        if ($actor->id === $agentId) {
            throw new AuthorizationException("Anti-Self-Approval: You cannot submit an achievement nomination for yourself.");
        }

        $agent = User::findOrFail($agentId);
        if (!$this->isAgentEligible($agent)) {
            throw new DomainException("Agent #{$agentId} is not eligible to receive achievements (requires active status and verified KYC).");
        }

        $achievement = AgentAchievement::findOrFail($achievementId);
        if (!$achievement->is_active) {
            throw new DomainException("Achievement '{$achievement->code}' is not active.");
        }
        if (!$achievement->is_discretionary) {
            throw new DomainException("Nominations can only be submitted for discretionary achievements.");
        }

        // Check if agent already has an active award for this slot
        $hasActiveAward = AgentAchievementAward::where('agent_id', $agentId)
            ->where('achievement_id', $achievementId)
            ->where('period_key', $periodKey)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveAward) {
            throw new DomainException("Agent #{$agentId} already holds an active award for achievement '{$achievement->code}' ({$periodKey}).");
        }

        $expiresAt = Carbon::now('Asia/Colombo')->addDays(30)->setTimezone('UTC');

        try {
            $request = AgentAchievementRequest::create([
                'achievement_id' => $achievementId,
                'agent_id' => $agentId,
                'requested_by' => $actor->id,
                'status' => 'pending',
                'justification' => $justification,
                'period_key' => $periodKey,
                'expires_at' => $expiresAt,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new DomainException("A pending nomination for this agent, achievement, and period already exists.");
            }
            throw $e;
        }

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'agent_achievement_request_submitted',
            'auditable_type' => AgentAchievementRequest::class,
            'auditable_id' => $request->id,
            'new_values' => [
                'request_id' => $request->id,
                'achievement_id' => $achievementId,
                'achievement_code' => $achievement->code,
                'agent_id' => $agentId,
                'requested_by' => $actor->id,
                'period_key' => $periodKey,
                'expires_at' => $expiresAt->toIso8601String(),
                'justification' => $justification,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return $request;
    }

    /**
     * Approve a discretionary achievement request.
     * Exclusively authorized for Business Owner.
     * Enforces two-transaction expiration safety, anti-self-approval, row locking, version snapshot,
     * award creation, request status transition, immutable audit logging, and post-commit notification.
     */
    public function approveDiscretionaryRequest(int $requestId, ?string $decisionNotes, User $owner): AgentAchievementAward
    {
        if (!$owner->isBusinessAuthority()) {
            throw new AuthorizationException("Unauthorized: Only the Business Owner has authority to approve achievement nominations.");
        }

        // 1. Independent Autocommit Verification Block for Expiration
        $isExpired = false;
        DB::transaction(function () use ($requestId, &$isExpired) {
            $req = AgentAchievementRequest::where('id', $requestId)->lockForUpdate()->firstOrFail();
            if ($req->status === 'pending' && $req->expires_at && Carbon::now()->greaterThanOrEqualTo($req->expires_at)) {
                $req->update([
                    'status' => 'expired',
                    'decision_notes' => 'Nomination expired prior to Owner decision.',
                ]);

                AuditLog::create([
                    'user_id' => null,
                    'action' => 'agent_achievement_request_expired',
                    'auditable_type' => AgentAchievementRequest::class,
                    'auditable_id' => $req->id,
                    'old_values' => ['status' => 'pending'],
                    'new_values' => ['status' => 'expired'],
                    'ip_address' => null,
                    'user_agent' => 'System/Auto',
                ]);

                $isExpired = true;
            }
        });

        if ($isExpired) {
            throw new DomainException("Cannot approve nomination: This request expired after 30 days and has been marked as expired.");
        }

        // 2. Primary Business Approval Transaction
        $expiredInsideTx = false;
        $result = DB::transaction(function () use ($requestId, $decisionNotes, $owner, &$expiredInsideTx) {
            $req = AgentAchievementRequest::where('id', $requestId)->lockForUpdate()->firstOrFail();

            if ($req->status !== 'pending') {
                throw new DomainException("Cannot process nomination: Current status is '{$req->status}'.");
            }

            // Re-verify expiration boundary under row lock inside the final approval transaction
            if ($req->expires_at && Carbon::now()->greaterThanOrEqualTo($req->expires_at)) {
                $req->update([
                    'status' => 'expired',
                    'decision_notes' => 'Nomination expired during final approval verification.',
                ]);

                AuditLog::create([
                    'user_id' => null,
                    'action' => 'agent_achievement_request_expired',
                    'auditable_type' => AgentAchievementRequest::class,
                    'auditable_id' => $req->id,
                    'old_values' => ['status' => 'pending'],
                    'new_values' => ['status' => 'expired'],
                    'ip_address' => null,
                    'user_agent' => 'System/Auto',
                ]);

                // Signal that expiration occurred so transaction COMMITS successfully rather than rolling back
                $expiredInsideTx = true;
                return null;
            }

            if ($owner->id === $req->requested_by) {
                throw new AuthorizationException("Anti-Self-Approval: The creator of a nomination cannot approve it. An independent Business Owner must approve this request, or use direct-grant.");
            }

            if ($owner->id === $req->agent_id) {
                throw new AuthorizationException("Anti-Self-Approval: A beneficiary cannot approve an award for their own account.");
            }

            $agent = User::findOrFail($req->agent_id);
            if (!$this->isAgentEligible($agent)) {
                throw new DomainException("Agent #{$agent->id} is no longer eligible to receive achievements.");
            }

            $achievement = AgentAchievement::findOrFail($req->achievement_id);
            $version = $this->resolveCurrentVersion($achievement);
            $periodKey = $req->period_key ?? 'lifetime';

            $existingAward = AgentAchievementAward::where('agent_id', $agent->id)
                ->where('achievement_id', $achievement->id)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($existingAward && $existingAward->status === 'active') {
                throw new DomainException("Agent #{$agent->id} already holds an active award for '{$achievement->code}' ({$periodKey}).");
            }

            if ($existingAward && $existingAward->status === 'revoked') {
                throw new DomainException("Cannot approve request: Award slot #{$existingAward->id} was previously revoked and must be reinstated explicitly by the Owner.");
            }

            $now = now();
            if ($existingAward) {
                // Update existing corrected award slot to active
                $oldStatus = $existingAward->status;
                $existingAward->update([
                    'version_id' => $version->id,
                    'status' => 'active',
                    'is_discretionary' => true,
                    'request_id' => $req->id,
                    'awarded_at' => $now,
                    'awarded_by' => $owner->id,
                    'metadata' => array_merge($existingAward->metadata ?? [], [
                        'approved_at' => $now->toIso8601String(),
                        'approved_by' => $owner->id,
                        'decision_notes' => $decisionNotes,
                        'justification' => $req->justification,
                    ]),
                ]);
                $award = $existingAward;
            } else {
                $award = AgentAchievementAward::create([
                    'agent_id' => $agent->id,
                    'achievement_id' => $achievement->id,
                    'version_id' => $version->id,
                    'period_key' => $periodKey,
                    'achieved_value' => '0.00',
                    'threshold_snapshot' => $version->threshold_value,
                    'status' => 'active',
                    'is_discretionary' => true,
                    'request_id' => $req->id,
                    'awarded_at' => $now,
                    'awarded_by' => $owner->id,
                    'metadata' => [
                        'approved_at' => $now->toIso8601String(),
                        'approved_by' => $owner->id,
                        'decision_notes' => $decisionNotes,
                        'justification' => $req->justification,
                    ],
                ]);
            }

            $req->update([
                'status' => 'approved',
                'decided_by' => $owner->id,
                'decided_at' => $now,
                'decision_notes' => $decisionNotes,
            ]);

            $audit = AuditLog::create([
                'user_id' => $owner->id,
                'action' => 'agent_achievement_request_approved',
                'auditable_type' => AgentAchievementRequest::class,
                'auditable_id' => $req->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => [
                    'status' => 'approved',
                    'decided_by' => $owner->id,
                    'decided_at' => $now->toIso8601String(),
                    'decision_notes' => $decisionNotes,
                    'award_id' => $award->id,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            AuditLog::create([
                'user_id' => $owner->id,
                'action' => 'agent_achievement_awarded',
                'auditable_type' => AgentAchievementAward::class,
                'auditable_id' => $award->id,
                'new_values' => [
                    'award_id' => $award->id,
                    'agent_id' => $agent->id,
                    'achievement_id' => $achievement->id,
                    'achievement_code' => $achievement->code,
                    'version_id' => $version->id,
                    'period_key' => $periodKey,
                    'is_discretionary' => true,
                    'request_id' => $req->id,
                    'awarded_by' => $owner->id,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return ['award' => $award, 'audit_id' => $audit->id];
        });

        if ($expiredInsideTx) {
            throw new DomainException("Cannot approve nomination: Expiry boundary reached during final approval verification.");
        }

        $award = $result['award'];
        $auditId = $result['audit_id'];
        $award->loadMissing(['agent', 'achievement']);

        DB::afterCommit(function () use ($award, $auditId) {
            try {
                $award->agent->notify(new AgentAchievementAwardedNotification($award, 'discretionary_approved', null, $auditId));
            } catch (\Exception $e) {
                Log::error("Failed to dispatch discretionary achievement approval notification: {$e->getMessage()}", [
                    'award_id' => $award->id,
                ]);
            }
        });

        return $award;
    }

    /**
     * Reject a discretionary achievement request.
     * Exclusively authorized for Business Owner.
     */
    public function rejectDiscretionaryRequest(int $requestId, string $decisionNotes, User $owner): AgentAchievementRequest
    {
        if (!$owner->isBusinessAuthority()) {
            throw new AuthorizationException("Unauthorized: Only the Business Owner has authority to reject achievement nominations.");
        }

        return DB::transaction(function () use ($requestId, $decisionNotes, $owner) {
            $req = AgentAchievementRequest::where('id', $requestId)->lockForUpdate()->firstOrFail();

            if ($req->status !== 'pending') {
                throw new DomainException("Cannot process nomination: Current status is '{$req->status}'.");
            }

            if ($owner->id === $req->requested_by) {
                throw new AuthorizationException("Anti-Self-Approval: The creator of a nomination cannot reject it.");
            }

            $now = now();
            $req->update([
                'status' => 'rejected',
                'decided_by' => $owner->id,
                'decided_at' => $now,
                'decision_notes' => $decisionNotes,
            ]);

            AuditLog::create([
                'user_id' => $owner->id,
                'action' => 'agent_achievement_request_rejected',
                'auditable_type' => AgentAchievementRequest::class,
                'auditable_id' => $req->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => [
                    'status' => 'rejected',
                    'decided_by' => $owner->id,
                    'decided_at' => $now->toIso8601String(),
                    'decision_notes' => $decisionNotes,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return $req;
        });
    }

    /**
     * Direct discretionary grant issued directly by the Business Owner without prior nomination.
     */
    public function directGrantDiscretionaryAward(int $agentId, int $achievementId, ?string $periodKey, string $justification, User $owner): AgentAchievementAward
    {
        if (!$owner->isBusinessAuthority()) {
            throw new AuthorizationException("Unauthorized: Only the Business Owner can issue direct discretionary achievement awards.");
        }

        if ($owner->id === $agentId) {
            throw new AuthorizationException("Anti-Self-Approval: The Business Owner cannot grant an achievement to their own account.");
        }

        $agent = User::findOrFail($agentId);
        if (!$this->isAgentEligible($agent)) {
            throw new DomainException("Agent #{$agentId} is not eligible to receive achievements.");
        }

        $achievement = AgentAchievement::findOrFail($achievementId);
        if (!$achievement->is_active) {
            throw new DomainException("Achievement '{$achievement->code}' is not active.");
        }
        if (!$achievement->is_discretionary) {
            throw new DomainException("Direct grants can only be issued for discretionary achievements.");
        }

        $periodKey = $periodKey ?? 'lifetime';

        $result = DB::transaction(function () use ($agent, $achievement, $periodKey, $justification, $owner) {
            $existingAward = AgentAchievementAward::where('agent_id', $agent->id)
                ->where('achievement_id', $achievement->id)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($existingAward && $existingAward->status === 'active') {
                throw new DomainException("Agent #{$agent->id} already holds an active award for '{$achievement->code}' ({$periodKey}).");
            }

            if ($existingAward && $existingAward->status === 'revoked') {
                throw new DomainException("Award slot #{$existingAward->id} was previously revoked. Use explicit Owner reinstatement instead.");
            }

            $version = $this->resolveCurrentVersion($achievement);
            $now = now();

            if ($existingAward) {
                $existingAward->update([
                    'version_id' => $version->id,
                    'status' => 'active',
                    'is_discretionary' => true,
                    'awarded_at' => $now,
                    'awarded_by' => $owner->id,
                    'metadata' => array_merge($existingAward->metadata ?? [], [
                        'direct_grant' => true,
                        'justification' => $justification,
                        'granted_by' => $owner->id,
                    ]),
                ]);
                $award = $existingAward;
            } else {
                $award = AgentAchievementAward::create([
                    'agent_id' => $agent->id,
                    'achievement_id' => $achievement->id,
                    'version_id' => $version->id,
                    'period_key' => $periodKey,
                    'achieved_value' => '0.00',
                    'threshold_snapshot' => $version->threshold_value,
                    'status' => 'active',
                    'is_discretionary' => true,
                    'request_id' => null,
                    'awarded_at' => $now,
                    'awarded_by' => $owner->id,
                    'metadata' => [
                        'direct_grant' => true,
                        'justification' => $justification,
                        'granted_by' => $owner->id,
                    ],
                ]);
            }

            $audit = AuditLog::create([
                'user_id' => $owner->id,
                'action' => 'agent_achievement_direct_granted',
                'auditable_type' => AgentAchievementAward::class,
                'auditable_id' => $award->id,
                'new_values' => [
                    'award_id' => $award->id,
                    'agent_id' => $agent->id,
                    'achievement_id' => $achievement->id,
                    'achievement_code' => $achievement->code,
                    'version_id' => $version->id,
                    'period_key' => $periodKey,
                    'is_discretionary' => true,
                    'justification' => $justification,
                    'awarded_by' => $owner->id,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return ['award' => $award, 'audit_id' => $audit->id];
        });

        $award = $result['award'];
        $auditId = $result['audit_id'];
        $award->loadMissing(['agent', 'achievement']);

        DB::afterCommit(function () use ($award, $auditId) {
            try {
                $award->agent->notify(new AgentAchievementAwardedNotification($award, 'direct_granted', null, $auditId));
            } catch (\Exception $e) {
                Log::error("Failed to dispatch direct grant notification: {$e->getMessage()}", [
                    'award_id' => $award->id,
                ]);
            }
        });

        return $award;
    }

    /**
     * Revoke an active or corrected achievement award.
     * Exclusively authorized for Business Owner.
     */
    public function revokeAward(int $awardId, string $reason, User $owner): AgentAchievementAward
    {
        if (!$owner->isBusinessAuthority()) {
            throw new AuthorizationException("Unauthorized: Only the Business Owner has authority to revoke achievement awards.");
        }

        $result = DB::transaction(function () use ($awardId, $reason, $owner) {
            $award = AgentAchievementAward::where('id', $awardId)->lockForUpdate()->firstOrFail();

            if (!in_array($award->status, ['active', 'corrected'], true)) {
                throw new DomainException("Award #{$awardId} cannot be revoked because its current status is '{$award->status}'.");
            }

            $oldStatus = $award->status;
            $now = now();

            $history = $award->metadata['history'] ?? [];
            $history[] = [
                'event' => 'revoked',
                'from_status' => $oldStatus,
                'to_status' => 'revoked',
                'timestamp' => $now->toIso8601String(),
                'actor_id' => $owner->id,
                'reason' => $reason,
            ];

            $award->update([
                'status' => 'revoked',
                'revoked_at' => $now,
                'revoked_by' => $owner->id,
                'revocation_reason' => $reason,
                'metadata' => array_merge($award->metadata ?? [], [
                    'history' => $history,
                ]),
            ]);

            $audit = AuditLog::create([
                'user_id' => $owner->id,
                'action' => 'agent_achievement_award_revoked',
                'auditable_type' => AgentAchievementAward::class,
                'auditable_id' => $award->id,
                'old_values' => [
                    'status' => $oldStatus,
                    'revoked_at' => null,
                ],
                'new_values' => [
                    'status' => 'revoked',
                    'revoked_at' => $now->toIso8601String(),
                    'revoked_by' => $owner->id,
                    'revocation_reason' => $reason,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return ['award' => $award, 'audit_id' => $audit->id];
        });

        $award = $result['award'];
        $auditId = $result['audit_id'];
        $award->loadMissing(['agent', 'achievement']);

        DB::afterCommit(function () use ($award, $reason, $auditId) {
            try {
                $award->agent->notify(new AgentAchievementRevokedNotification($award, $reason, $auditId));
            } catch (\Exception $e) {
                Log::error("Failed to dispatch achievement revocation notification: {$e->getMessage()}", [
                    'award_id' => $award->id,
                ]);
            }
        });

        return $award;
    }

    /**
     * Explicitly reinstate a previously revoked achievement award.
     * Exclusively authorized for Business Owner.
     */
    public function reinstateAward(int $awardId, string $reason, User $owner): AgentAchievementAward
    {
        if (!$owner->isBusinessAuthority()) {
            throw new AuthorizationException("Unauthorized: Only the Business Owner has authority to reinstate achievement awards.");
        }

        $result = DB::transaction(function () use ($awardId, $reason, $owner) {
            $award = AgentAchievementAward::where('id', $awardId)->lockForUpdate()->firstOrFail();

            if ($award->status !== 'revoked') {
                throw new DomainException("Award #{$awardId} cannot be reinstated because its current status is '{$award->status}'.");
            }

            $agent = User::findOrFail($award->agent_id);
            if (!$this->isAgentEligible($agent)) {
                throw new DomainException("Cannot reinstate award: Agent #{$agent->id} is not currently eligible.");
            }

            $now = now();
            $history = $award->metadata['history'] ?? [];
            $history[] = [
                'event' => 'reinstated',
                'from_status' => 'revoked',
                'to_status' => 'active',
                'timestamp' => $now->toIso8601String(),
                'actor_id' => $owner->id,
                'reason' => $reason,
            ];

            $award->update([
                'status' => 'active',
                'awarded_at' => $now,
                'awarded_by' => $owner->id,
                'revoked_at' => null,
                'revoked_by' => null,
                'revocation_reason' => null,
                'metadata' => array_merge($award->metadata ?? [], [
                    'history' => $history,
                    'reinstated_at' => $now->toIso8601String(),
                    'reinstated_by' => $owner->id,
                    'reinstatement_reason' => $reason,
                ]),
            ]);

            $audit = AuditLog::create([
                'user_id' => $owner->id,
                'action' => 'agent_achievement_award_reinstated',
                'auditable_type' => AgentAchievementAward::class,
                'auditable_id' => $award->id,
                'old_values' => [
                    'status' => 'revoked',
                ],
                'new_values' => [
                    'status' => 'active',
                    'reinstated_at' => $now->toIso8601String(),
                    'reinstated_by' => $owner->id,
                    'reinstatement_reason' => $reason,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return ['award' => $award, 'audit_id' => $audit->id];
        });

        $award = $result['award'];
        $auditId = $result['audit_id'];
        $award->loadMissing(['agent', 'achievement']);

        DB::afterCommit(function () use ($award, $reason, $auditId) {
            try {
                $award->agent->notify(new AgentAchievementReinstatedNotification($award, $reason, $auditId));
            } catch (\Exception $e) {
                Log::error("Failed to dispatch achievement reinstatement notification: {$e->getMessage()}", [
                    'award_id' => $award->id,
                ]);
            }
        });

        return $award;
    }

    /**
     * Prune expired discretionary achievement requests.
     */
    public function pruneExpiredRequests(): int
    {
        $expiredCount = 0;
        $overdueRequests = AgentAchievementRequest::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($overdueRequests as $req) {
            DB::transaction(function () use ($req) {
                $locked = AgentAchievementRequest::where('id', $req->id)->lockForUpdate()->first();
                if ($locked && $locked->status === 'pending' && $locked->expires_at && Carbon::now()->greaterThan($locked->expires_at)) {
                    $locked->update([
                        'status' => 'expired',
                        'decision_notes' => 'Nomination expired automatically after 30 days.',
                    ]);

                    AuditLog::create([
                        'user_id' => null,
                        'action' => 'agent_achievement_request_expired',
                        'auditable_type' => AgentAchievementRequest::class,
                        'auditable_id' => $locked->id,
                        'old_values' => ['status' => 'pending'],
                        'new_values' => ['status' => 'expired'],
                        'ip_address' => null,
                        'user_agent' => 'CLI/System',
                    ]);
                }
            });
            $expiredCount++;
        }

        return $expiredCount;
    }

    /**
     * Resolve the current immutable version for an achievement.
     */
    protected function resolveCurrentVersion(AgentAchievement $achievement): AgentAchievementVersion
    {
        $version = AgentAchievementVersion::where('achievement_id', $achievement->id)
            ->where('version', $achievement->current_version)
            ->first();

        if (!$version) {
            $version = AgentAchievementVersion::where('achievement_id', $achievement->id)
                ->orderByDesc('version')
                ->first();
        }

        if (!$version) {
            throw new DomainException("Integrity error: Achievement '{$achievement->code}' has no version records.");
        }

        return $version;
    }

    /**
     * Check if a QueryException represents a database unique constraint violation across MySQL and SQLite.
     */
    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->getCode();
        $message = $e->getMessage();

        // MySQL 1062, SQLSTATE 23000, or SQLite "UNIQUE constraint failed"
        return $sqlState === '23000'
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed')
            || (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062);
    }
}
