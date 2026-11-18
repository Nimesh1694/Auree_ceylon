<?php

namespace App\Services;

use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserClosure;
use App\Notifications\AgentTargetAssignedNotification;
use App\Notifications\AgentTargetUpdatedNotification;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class AgentSalesTargetService
{
    /**
     * Create a sales target for a current or future month and record an immutable audit log atomically.
     * Enforces active agent role and rejects closed historical months.
     *
     * @param array{agent_id: int, target_month: string, sales_target: string|int} $data
     * @param User $actor The user creating the target (mandatory)
     * @return AgentSalesTarget
     * @throws InvalidArgumentException|AuthorizationException|Throwable
     */
    public function createTarget(array $data, User $actor): AgentSalesTarget
    {
        if (!$actor->exists || $actor->id <= 0 || ((string) ($actor->status->value ?? $actor->status)) !== 'active') {
            throw new AuthorizationException("Only active actors are authorized to establish sales targets.");
        }

        return DB::transaction(function () use ($data, $actor) {
            // Re-evaluate current Colombo month inside transaction
            $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
            if ($data['target_month'] < $currentColomboMonth) {
                throw new AuthorizationException("Standard target creation is not permitted for closed historical months ({$data['target_month']} < {$currentColomboMonth}).");
            }

            // Active agent check
            $targetAgent = User::findOrFail($data['agent_id']);
            if (!$targetAgent->isAgent() || (string) ($targetAgent->status->value ?? $targetAgent->status) !== 'active') {
                throw new InvalidArgumentException("Sales targets may only be established for active agents.");
            }

            $normalizedTarget = AgentAnalyticsService::normalizeDecimalString($data['sales_target'], false);

            $target = AgentSalesTarget::create([
                'agent_id' => $data['agent_id'],
                'target_month' => $data['target_month'],
                'sales_target' => $normalizedTarget,
                'created_by' => $actor->id,
            ]);

            // Explicit actor attribution across HTTP, CLI, and test execution contexts
            $auditLog = AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'agent_sales_target_created',
                'auditable_type' => AgentSalesTarget::class,
                'auditable_id' => $target->id,
                'old_values' => null,
                'new_values' => [
                    'agent_id' => $target->agent_id,
                    'target_month' => $target->target_month,
                    'sales_target' => (string) $target->sales_target,
                    'created_by' => $actor->id,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            DB::afterCommit(function () use ($target, $actor, $auditLog) {
                $this->dispatchTargetNotification($target, $actor, $auditLog);
            });

            return $target;
        });
    }

    /**
     * Update an existing sales target with row-level locking and record an immutable audit log atomically.
     * Enforces universal historical locking (rejecting even Owners) and mandatory adjustment reasons.
     *
     * @param AgentSalesTarget $target
     * @param array{target_month?: string, sales_target: string|int, reason?: string} $data
     * @param User $actor The user updating the target (mandatory)
     * @return AgentSalesTarget
     * @throws InvalidArgumentException|AuthorizationException|Throwable
     */
    public function updateTarget(AgentSalesTarget $target, array $data, User $actor): AgentSalesTarget
    {
        if (!$actor->exists || $actor->id <= 0) {
            throw new InvalidArgumentException("A valid existing actor is required for sales target update.");
        }

        // Mandatory adjustment reason guard
        $reason = trim($data['reason'] ?? '');
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException("A valid adjustment reason (minimum 5 characters) is required.");
        }

        return DB::transaction(function () use ($target, $data, $actor, $reason) {
            // Retrieve fresh record with row-level lock for concurrent update protection
            /** @var AgentSalesTarget $lockedTarget */
            $lockedTarget = AgentSalesTarget::query()->lockForUpdate()->findOrFail($target->id);

            // Re-evaluate current Colombo month inside transaction AFTER row lock is acquired
            $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
            if ($lockedTarget->target_month < $currentColomboMonth) {
                throw new AuthorizationException("Targets in closed historical periods are locked. Modifications must be executed via the explicit historical override workflow.");
            }

            // Provenance and authority check
            $this->assertActorCanModifyTarget($lockedTarget, $actor);

            $oldValues = [
                'agent_id' => $lockedTarget->agent_id,
                'target_month' => $lockedTarget->target_month,
                'sales_target' => (string) $lockedTarget->sales_target,
                'created_by' => $lockedTarget->created_by,
            ];

            if (isset($data['target_month']) && $data['target_month'] !== $lockedTarget->target_month) {
                if ($actor->isHighLevelAgent() && !$actor->isManagement()) {
                    throw new AuthorizationException("High-Level Agents cannot alter target months on existing assignments.");
                }
                $lockedTarget->target_month = $data['target_month'];
            }
            if (isset($data['sales_target'])) {
                $lockedTarget->sales_target = AgentAnalyticsService::normalizeDecimalString($data['sales_target'], false);
            }

            $lockedTarget->save();

            $newValues = [
                'agent_id' => $lockedTarget->agent_id,
                'target_month' => $lockedTarget->target_month,
                'sales_target' => (string) $lockedTarget->sales_target,
                'created_by' => $lockedTarget->created_by,
                'reason' => $reason,
                'updated_by' => $actor->id,
            ];

            $action = ($actor->isHighLevelAgent() && !$actor->isManagement())
                ? 'agent_sales_target_hla_updated'
                : 'agent_sales_target_updated';

            $auditLog = AuditLog::create([
                'user_id' => $actor->id,
                'action' => $action,
                'auditable_type' => AgentSalesTarget::class,
                'auditable_id' => $lockedTarget->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            DB::afterCommit(function () use ($lockedTarget, $actor, $auditLog) {
                $this->dispatchTargetNotification($lockedTarget, $actor, $auditLog);
            });

            return $lockedTarget;
        });
    }

    /**
     * Dedicated Owner-Exclusive Historical Override Method to adjust an existing target in a closed month.
     *
     * @param AgentSalesTarget $target
     * @param string|int $newSalesTarget
     * @param string $overrideReason
     * @param User $owner
     * @return AgentSalesTarget
     * @throws AuthorizationException|InvalidArgumentException|Throwable
     */
    public function overrideHistoricalTarget(
        AgentSalesTarget $target,
        string|int $newSalesTarget,
        string $overrideReason,
        User $owner
    ): AgentSalesTarget {
        if (!$owner->exists || $owner->id <= 0 || !$owner->isOwner() || ((string) ($owner->status->value ?? $owner->status)) !== 'active') {
            throw new AuthorizationException("Only the Business Owner possesses historical override authority.");
        }

        $cleanReason = trim($overrideReason);
        if (mb_strlen($cleanReason) < 10) {
            throw new InvalidArgumentException("A comprehensive override reason (minimum 10 characters) is required.");
        }

        return DB::transaction(function () use ($target, $newSalesTarget, $cleanReason, $owner) {
            /** @var AgentSalesTarget $lockedTarget */
            $lockedTarget = AgentSalesTarget::query()->lockForUpdate()->findOrFail($target->id);

            // Re-evaluate current Colombo month inside transaction AFTER row lock is acquired
            $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
            if ($lockedTarget->target_month >= $currentColomboMonth) {
                throw new InvalidArgumentException("Historical override is restricted to closed past months. Target month {$lockedTarget->target_month} is current or future; use standard target update.");
            }

            $oldValues = [
                'agent_id' => $lockedTarget->agent_id,
                'target_month' => $lockedTarget->target_month,
                'sales_target' => (string) $lockedTarget->sales_target,
                'created_by' => $lockedTarget->created_by,
            ];

            $normalizedAmount = AgentAnalyticsService::normalizeDecimalString($newSalesTarget, false);
            $lockedTarget->sales_target = $normalizedAmount;
            $lockedTarget->save();

            $newValues = [
                'agent_id' => $lockedTarget->agent_id,
                'target_month' => $lockedTarget->target_month,
                'sales_target' => (string) $lockedTarget->sales_target,
                'created_by' => $lockedTarget->created_by,
                'override_reason' => $cleanReason,
                'is_historical_override' => true,
                'overridden_by' => $owner->id,
                'overridden_at' => Carbon::now(PeriodBoundaryService::TIMEZONE)->toIso8601String(),
            ];

            $auditLog = AuditLog::create([
                'user_id' => $owner->id,
                'action' => 'agent_sales_target_historical_override',
                'auditable_type' => AgentSalesTarget::class,
                'auditable_id' => $lockedTarget->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            DB::afterCommit(function () use ($lockedTarget, $owner, $auditLog) {
                $this->dispatchTargetNotification($lockedTarget, $owner, $auditLog);
            });

            return $lockedTarget;
        });
    }

    /**
     * Dedicated Owner-Exclusive Historical Target Creation Method for establishing a missing target in a closed month.
     *
     * @param array{agent_id: int, target_month: string, sales_target: string|int} $data
     * @param string $overrideReason
     * @param User $owner
     * @return AgentSalesTarget
     * @throws AuthorizationException|InvalidArgumentException|Throwable
     */
    public function createHistoricalOverrideTarget(
        array $data,
        string $overrideReason,
        User $owner
    ): AgentSalesTarget {
        if (!$owner->exists || $owner->id <= 0 || !$owner->isOwner() || ((string) ($owner->status->value ?? $owner->status)) !== 'active') {
            throw new AuthorizationException("Only the Business Owner possesses historical override authority.");
        }

        $cleanReason = trim($overrideReason);
        if (mb_strlen($cleanReason) < 10) {
            throw new InvalidArgumentException("A comprehensive override reason (minimum 10 characters) is required.");
        }

        return DB::transaction(function () use ($data, $cleanReason, $owner) {
            // Re-evaluate current Colombo month inside transaction
            $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
            if ($data['target_month'] >= $currentColomboMonth) {
                throw new InvalidArgumentException("Historical creation is restricted to closed past months. Target month {$data['target_month']} is current or future; use standard target creation.");
            }

            // Active agent check
            $targetAgent = User::findOrFail($data['agent_id']);
            if (!$targetAgent->isAgent() || (string) ($targetAgent->status->value ?? $targetAgent->status) !== 'active') {
                throw new InvalidArgumentException("Sales targets may only be established for active agents.");
            }

            // Duplicate protection check
            $exists = AgentSalesTarget::where('agent_id', $data['agent_id'])
                ->where('target_month', $data['target_month'])
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException("A sales target already exists for agent {$data['agent_id']} in month {$data['target_month']}. Use overrideHistoricalTarget() to adjust an existing target.");
            }

            $normalizedTarget = AgentAnalyticsService::normalizeDecimalString($data['sales_target'], false);

            $target = AgentSalesTarget::create([
                'agent_id' => $data['agent_id'],
                'target_month' => $data['target_month'],
                'sales_target' => $normalizedTarget,
                'created_by' => $owner->id,
            ]);

            $auditLog = AuditLog::create([
                'user_id' => $owner->id,
                'action' => 'agent_sales_target_historical_created',
                'auditable_type' => AgentSalesTarget::class,
                'auditable_id' => $target->id,
                'old_values' => null,
                'new_values' => [
                    'agent_id' => $target->agent_id,
                    'target_month' => $target->target_month,
                    'sales_target' => (string) $target->sales_target,
                    'created_by' => $owner->id,
                    'override_reason' => $cleanReason,
                    'is_historical_override' => true,
                    'created_at' => Carbon::now(PeriodBoundaryService::TIMEZONE)->toIso8601String(),
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            DB::afterCommit(function () use ($target, $owner, $auditLog) {
                $this->dispatchTargetNotification($target, $owner, $auditLog);
            });

            return $target;
        });
    }

    /**
     * Assign a sales target to an active downline LLA by an acting High-Level Agent.
     * Enforces active HLA actor, active LLA recipient, hierarchy membership (depth > 0),
     * open current/future month, and creates an immutable audit log.
     *
     * @param User $hla The acting High-Level Agent
     * @param int $llaId The downline Low-Level Agent ID
     * @param string $targetMonth YYYY-MM
     * @param string|int $salesTarget
     * @param string $reason Non-empty rationale (minimum 5 chars)
     * @return AgentSalesTarget
     * @throws AuthorizationException|InvalidArgumentException|Throwable
     */
    public function assignDownlineTarget(
        User $hla,
        int $llaId,
        string $targetMonth,
        string|int $salesTarget,
        string $reason
    ): AgentSalesTarget {
        if (!$hla->exists || $hla->id <= 0 || !$hla->isHighLevelAgent() || ((string) ($hla->status->value ?? $hla->status)) !== 'active') {
            throw new AuthorizationException("Only active High-Level Agents are authorized to assign downline sales targets.");
        }

        if ($hla->id === $llaId) {
            throw new AuthorizationException("High-Level Agents cannot assign targets to themselves.");
        }

        $cleanReason = trim($reason);
        if (mb_strlen($cleanReason) < 5) {
            throw new InvalidArgumentException("A valid assignment reason (minimum 5 characters) is required.");
        }

        return DB::transaction(function () use ($hla, $llaId, $targetMonth, $salesTarget, $cleanReason) {
            // Re-evaluate current Colombo month inside transaction
            $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
            if ($targetMonth < $currentColomboMonth) {
                throw new AuthorizationException("Target creation is not permitted for closed historical months ({$targetMonth} < {$currentColomboMonth}).");
            }

            // Recipient validation with row-level lock against concurrent status/hierarchy moves
            $recipient = User::query()->lockForUpdate()->findOrFail($llaId);
            if (!$recipient->isLowLevelAgent()) {
                throw new AuthorizationException("Targets can only be assigned to Low-Level Agents.");
            }

            if (((string) ($recipient->status->value ?? $recipient->status)) !== 'active') {
                throw new AuthorizationException("Targets can only be assigned to active agents.");
            }

            if ($recipient->agentProfile && $recipient->agentProfile->kyc_status === 'rejected') {
                throw new AuthorizationException("Targets cannot be assigned to agents with rejected KYC verification status.");
            }

            // Strict hierarchy validation (depth > 0)
            $isDownline = UserClosure::where('ancestor_id', $hla->id)
                ->where('descendant_id', $recipient->id)
                ->where('depth', '>', 0)
                ->exists();

            if (!$isDownline) {
                throw new AuthorizationException("Target recipient is not within your downline hierarchy.");
            }

            // Duplicate target guard
            $exists = AgentSalesTarget::where('agent_id', $recipient->id)
                ->where('target_month', $targetMonth)
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException("A sales target already exists for agent {$recipient->id} in month {$targetMonth}.");
            }

            $normalizedTarget = AgentAnalyticsService::normalizeDecimalString($salesTarget, false);

            $target = AgentSalesTarget::create([
                'agent_id' => $recipient->id,
                'target_month' => $targetMonth,
                'sales_target' => $normalizedTarget,
                'created_by' => $hla->id,
            ]);

            $auditLog = AuditLog::create([
                'user_id' => $hla->id,
                'action' => 'agent_sales_target_hla_assigned',
                'auditable_type' => AgentSalesTarget::class,
                'auditable_id' => $target->id,
                'old_values' => null,
                'new_values' => [
                    'agent_id' => $target->agent_id,
                    'target_month' => $target->target_month,
                    'sales_target' => (string) $target->sales_target,
                    'created_by' => $hla->id,
                    'reason' => $cleanReason,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            DB::afterCommit(function () use ($target, $hla, $auditLog) {
                $this->dispatchTargetNotification($target, $hla, $auditLog);
            });

            return $target;
        });
    }

    /**
     * Dedicated downline target update wrapper for High-Level Agents.
     *
     * @param User $hla
     * @param AgentSalesTarget $target
     * @param array{sales_target: string|int, reason: string} $data
     * @return AgentSalesTarget
     * @throws AuthorizationException|InvalidArgumentException|Throwable
     */
    public function updateDownlineTarget(
        User $hla,
        AgentSalesTarget $target,
        array $data
    ): AgentSalesTarget {
        if (!$hla->exists || $hla->id <= 0 || !$hla->isHighLevelAgent() || ((string) ($hla->status->value ?? $hla->status)) !== 'active') {
            throw new AuthorizationException("Only active High-Level Agents are authorized to modify downline sales targets.");
        }

        return $this->updateTarget($target, $data, $hla);
    }

    /**
     * Enforce Provenance & Hierarchy Authority on Target Mutation.
     * Fails closed if audit history is missing, legacy, or inconclusive.
     *
     * @param AgentSalesTarget $lockedTarget
     * @param User $actor
     * @return void
     * @throws AuthorizationException
     */
    public function assertActorCanModifyTarget(AgentSalesTarget $lockedTarget, User $actor): void
    {
        // 1. Universal Actor Active Status Guard (applies across Management and Agents)
        if (!$actor->exists || $actor->id <= 0 || ((string) ($actor->status->value ?? $actor->status)) !== 'active') {
            throw new AuthorizationException("Inactive actors are not authorized to modify sales targets.");
        }

        // 2. Active Management Authority Guard
        if ($actor->isManagement()) {
            return; // Active Management holds superior modification authority
        }

        // 3. High-Level Agent Authority Guard
        if ($actor->isHighLevelAgent()) {
            // Provenance Guard: Check if target is Management-Controlled
            if ($this->isTargetManagementControlled($lockedTarget)) {
                throw new AuthorizationException("This target is under management control and cannot be modified by a High-Level Agent.");
            }

            // Target recipient must be an active Low-Level Agent with valid KYC
            $targetAgent = User::query()->lockForUpdate()->find($lockedTarget->agent_id);
            if (!$targetAgent || !$targetAgent->isLowLevelAgent() || ((string) ($targetAgent->status->value ?? $targetAgent->status)) !== 'active') {
                throw new AuthorizationException("Targets can only be managed for active Low-Level Agents.");
            }

            if ($targetAgent->agentProfile && $targetAgent->agentProfile->kyc_status === 'rejected') {
                throw new AuthorizationException("Targets cannot be managed for agents with rejected KYC verification status.");
            }

            // Hierarchy Guard: Must be in acting HLA's downline closure tree
            $isDownline = UserClosure::where('ancestor_id', $actor->id)
                ->where('descendant_id', $lockedTarget->agent_id)
                ->where('depth', '>', 0)
                ->exists();

            if (!$isDownline) {
                throw new AuthorizationException("You are not authorized to modify targets for agents outside your downline.");
            }

            return;
        }

        throw new AuthorizationException("Unauthorized to modify sales targets.");
    }

    /**
     * Determine whether a sales target is Management-Controlled.
     * Scans complete audit trail and strictly fails closed on legacy/missing audit records.
     *
     * @param AgentSalesTarget $lockedTarget
     * @return bool True if Management-Controlled or provenance is inconclusive
     */
    public function isTargetManagementControlled(AgentSalesTarget $lockedTarget): bool
    {
        // 1. If original creator is Management, permanently Management-Controlled
        if ($lockedTarget->creator && $lockedTarget->creator->isManagement()) {
            return true;
        }

        // 2. Query ALL audit entries for this target
        $managementActions = [
            'agent_sales_target_created',
            'agent_sales_target_updated',
            'agent_sales_target_historical_created',
            'agent_sales_target_historical_override',
            'agent_sales_target_rolled_over',
        ];

        $hasManagementAudit = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $lockedTarget->id)
            ->whereIn('action', $managementActions)
            ->exists();

        if ($hasManagementAudit) {
            return true;
        }

        // 3. Fail-Closed Guard: If NO verified HLA creation audit event exists, fail closed
        $hasVerifiedHlaCreation = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $lockedTarget->id)
            ->where('action', 'agent_sales_target_hla_assigned')
            ->exists();

        if (!$hasVerifiedHlaCreation) {
            return true; // Fail closed: Inconclusive or legacy provenance treated as Management-Controlled
        }

        return false;
    }

    /**
     * Bulk rollover monthly agent sales targets from a source month to a target month in bounded batches.
     * Enforces active management authority, open current/future target period, recipient eligibility,
     * duplicate constraint discrimination, audit logging, and post-commit notifications.
     *
     * @param string $sourceMonth YYYY-MM
     * @param string $targetMonth YYYY-MM
     * @param User $actor Acting Management User
     * @param int $batchSize Chunk size (default 100)
     * @return array{processed: int, created: int, skipped_existing: int, skipped_inactive: int}
     * @throws AuthorizationException|InvalidArgumentException|Throwable
     */
    public function rolloverMonthlyTargets(
        string $sourceMonth,
        string $targetMonth,
        User $actor,
        int $batchSize = 100
    ): array {
        if (!$actor->exists || $actor->id <= 0 || !$actor->isManagement() || ((string) ($actor->status->value ?? $actor->status)) !== 'active') {
            throw new AuthorizationException("Only active management personnel are authorized to execute bulk target rollover.");
        }

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $sourceMonth) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $targetMonth)) {
            throw new InvalidArgumentException("Both source month and target month must be provided in valid YYYY-MM format.");
        }

        // Strict consecutive month validation: Target month must strictly be the immediately following month of source month
        $expectedNextMonth = Carbon::createFromFormat('Y-m', $sourceMonth, PeriodBoundaryService::TIMEZONE)->addMonth()->format('Y-m');
        if ($targetMonth !== $expectedNextMonth) {
            throw new InvalidArgumentException("Target month ({$targetMonth}) must be the immediately following month of source month ({$sourceMonth}). Expected: {$expectedNextMonth}.");
        }

        $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
        if ($targetMonth < $currentColomboMonth) {
            throw new AuthorizationException("Bulk rollover is restricted to current or future periods ({$targetMonth} < {$currentColomboMonth}).");
        }

        $summary = [
            'processed' => 0,
            'created' => 0,
            'skipped_existing' => 0,
            'skipped_inactive' => 0,
        ];

        $effectiveBatchSize = max(1, min($batchSize, 500));

        AgentSalesTarget::where('target_month', $sourceMonth)
            ->orderBy('id')
            ->chunk($effectiveBatchSize, function ($batch) use ($targetMonth, $actor, &$summary) {
                foreach ($batch as $sourceTarget) {
                    $summary['processed']++;

                    /** @var User|null $recipient */
                    $recipient = User::find($sourceTarget->agent_id);
                    if (!$recipient || !$recipient->isAgent() || ((string) ($recipient->status->value ?? $recipient->status)) !== 'active') {
                        $summary['skipped_inactive']++;
                        continue;
                    }

                    if ($recipient->agentProfile && $recipient->agentProfile->kyc_status === 'rejected') {
                        $summary['skipped_inactive']++;
                        continue;
                    }

                    // Pre-check to skip existing targets without initiating unnecessary database transaction
                    $exists = AgentSalesTarget::where('agent_id', $sourceTarget->agent_id)
                        ->where('target_month', $targetMonth)
                        ->exists();

                    if ($exists) {
                        $summary['skipped_existing']++;
                        continue;
                    }

                    // Atomic creation with unique constraint conflict discrimination
                    try {
                        DB::transaction(function () use ($sourceTarget, $targetMonth, $actor, &$summary) {
                            $newTarget = AgentSalesTarget::create([
                                'agent_id' => $sourceTarget->agent_id,
                                'target_month' => $targetMonth,
                                'sales_target' => (string) $sourceTarget->sales_target,
                                'created_by' => $actor->id,
                            ]);

                            $auditLog = AuditLog::create([
                                'user_id' => $actor->id,
                                'action' => 'agent_sales_target_rolled_over',
                                'auditable_type' => AgentSalesTarget::class,
                                'auditable_id' => $newTarget->id,
                                'old_values' => [
                                    'source_target_id' => $sourceTarget->id,
                                    'source_month' => $sourceTarget->target_month,
                                    'sales_target' => (string) $sourceTarget->sales_target,
                                ],
                                'new_values' => [
                                    'agent_id' => $newTarget->agent_id,
                                    'target_month' => $newTarget->target_month,
                                    'sales_target' => (string) $newTarget->sales_target,
                                    'created_by' => $actor->id,
                                    'rolled_over_from' => $sourceTarget->id,
                                ],
                                'ip_address' => request()?->ip(),
                                'user_agent' => request()?->userAgent(),
                            ]);

                            DB::afterCommit(function () use ($newTarget, $actor, $auditLog) {
                                $this->dispatchTargetNotification($newTarget, $actor, $auditLog);
                            });

                            $summary['created']++;
                        });
                    } catch (Throwable $e) {
                        if ($this->isTargetUniqueConstraintConflict($e)) {
                            $summary['skipped_existing']++;
                        } else {
                            throw $e;
                        }
                    }
                }
            });

        return $summary;
    }

    /**
     * Dispatch an idempotent database notification for a sales target lifecycle event.
     * Enforces active recipient status, non-rejected KYC, and database-level idempotency.
     *
     * @param AgentSalesTarget $target
     * @param User $actor
     * @param AuditLog $auditLog
     * @return void
     */
    public function dispatchTargetNotification(
        AgentSalesTarget $target,
        User $actor,
        AuditLog $auditLog
    ): void {
        $recipient = $target->agent ?? User::find($target->agent_id);
        if (!$recipient || !$recipient->isAgent()) {
            return;
        }

        // Active status and non-rejected KYC verification
        if (((string) ($recipient->status->value ?? $recipient->status)) !== 'active') {
            return;
        }
        if ($recipient->agentProfile && $recipient->agentProfile->kyc_status === 'rejected') {
            return;
        }

        $action = $auditLog->action;
        $notificationClass = match ($action) {
            'agent_sales_target_updated',
            'agent_sales_target_hla_updated',
            'agent_sales_target_historical_override' => AgentTargetUpdatedNotification::class,
            default => AgentTargetAssignedNotification::class,
        };

        $notification = new $notificationClass($target, $actor, $auditLog);

        // Idempotency pre-check to avoid redundant insert queries
        if (DB::table('notifications')->where('id', $notification->id)->exists()) {
            return;
        }

        try {
            $recipient->notify($notification);
        } catch (Throwable $e) {
            // If primary key collision occurs due to concurrent notification dispatch, safely ignore
            if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() == 1062) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Discriminate database unique constraint conflicts across MySQL and SQLite.
     * Guarantees foreign key, not null, or unrelated database errors are never swallowed.
     *
     * @param Throwable $e
     * @return bool
     */
    public function isTargetUniqueConstraintConflict(Throwable $e): bool
    {
        $message = $e->getMessage();
        $code = (int) $e->getCode();

        // Must NOT be foreign key or not-null constraint violation
        if (str_contains($message, 'FOREIGN KEY') || str_contains($message, 'NOT NULL')) {
            return false;
        }

        // MySQL error 1062 (ER_DUP_ENTRY) or SQLSTATE 23000 duplicate entry
        if ($code === 1062 || str_contains($message, '1062 Duplicate entry') || str_contains($message, 'Duplicate entry')) {
            return true;
        }

        // SQLite error 19 (SQLITE_CONSTRAINT) specifically for UNIQUE constraint
        if (str_contains($message, 'UNIQUE constraint failed') || ($code === 19 && str_contains($message, 'UNIQUE'))) {
            return true;
        }

        return false;
    }
}
