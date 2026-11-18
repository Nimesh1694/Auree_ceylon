<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentAnnouncement;
use App\Models\AgentAnnouncementRead;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentAnnouncementService
{
    /**
     * Create a new announcement in draft status.
     */
    public function createDraft(array $data, User $creator): AgentAnnouncement
    {
        return DB::transaction(function () use ($data, $creator) {
            $announcement = AgentAnnouncement::create([
                'title_en' => $data['title_en'],
                'title_si' => $data['title_si'] ?? null,
                'content_en' => $data['content_en'],
                'content_si' => $data['content_si'] ?? null,
                'target_audience' => $data['target_audience'] ?? AgentAnnouncement::AUDIENCE_ALL,
                'priority' => $data['priority'] ?? AgentAnnouncement::PRIORITY_NORMAL,
                'status' => AgentAnnouncement::STATUS_DRAFT,
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                'pin_order' => (int) ($data['pin_order'] ?? 0),
                'expires_at' => isset($data['expires_at']) && !empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
                'created_by' => $creator->id,
            ]);

            AuditLog::log(
                action: 'agent_announcement_created',
                model: $announcement,
                oldValues: null,
                newValues: [
                    'title_en' => $announcement->title_en,
                    'target_audience' => $announcement->target_audience,
                    'priority' => $announcement->priority,
                    'status' => $announcement->status,
                    'is_pinned' => $announcement->is_pinned,
                ]
            );

            return $announcement;
        });
    }

    /**
     * Update an announcement.
     * Target audience is strictly immutable once published.
     */
    public function update(AgentAnnouncement $announcement, array $data, User $updater): AgentAnnouncement
    {
        return DB::transaction(function () use ($announcement, $data) {
            $isPublished = in_array($announcement->status, [AgentAnnouncement::STATUS_PUBLISHED, AgentAnnouncement::STATUS_ARCHIVED], true);

            if ($isPublished && isset($data['target_audience']) && $data['target_audience'] !== $announcement->target_audience) {
                throw ValidationException::withMessages([
                    'target_audience' => ['The target audience cannot be changed once an announcement is published.'],
                ]);
            }

            $oldValues = $announcement->only([
                'title_en',
                'title_si',
                'content_en',
                'content_si',
                'target_audience',
                'priority',
                'is_pinned',
                'pin_order',
                'expires_at',
            ]);

            $fillData = [
                'title_en' => $data['title_en'],
                'title_si' => $data['title_si'] ?? null,
                'content_en' => $data['content_en'],
                'content_si' => $data['content_si'] ?? null,
                'priority' => $data['priority'] ?? $announcement->priority,
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                'pin_order' => (int) ($data['pin_order'] ?? 0),
                'expires_at' => isset($data['expires_at']) && !empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            ];

            if (!$isPublished && isset($data['target_audience'])) {
                $fillData['target_audience'] = $data['target_audience'];
            }

            $announcement->update($fillData);

            $newValues = $announcement->only(array_keys($fillData));

            AuditLog::log(
                action: 'agent_announcement_updated',
                model: $announcement,
                oldValues: $oldValues,
                newValues: $newValues
            );

            return $announcement;
        });
    }

    /**
     * Publish an announcement immediately.
     */
    public function publishNow(AgentAnnouncement $announcement, User $publisher): AgentAnnouncement
    {
        return DB::transaction(function () use ($announcement, $publisher) {
            $oldStatus = $announcement->status;

            $announcement->update([
                'status' => AgentAnnouncement::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by' => $publisher->id,
                'scheduled_at' => null,
                'scheduled_by' => null,
            ]);

            AuditLog::log(
                action: 'agent_announcement_published_manual',
                model: $announcement,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => AgentAnnouncement::STATUS_PUBLISHED,
                    'published_at' => now()->toIso8601String(),
                    'published_by' => $publisher->id,
                    'trigger' => 'manual_admin',
                ]
            );

            return $announcement;
        });
    }

    /**
     * Schedule an announcement for automated future publication.
     */
    public function schedule(AgentAnnouncement $announcement, Carbon $scheduledAt, User $scheduler): AgentAnnouncement
    {
        if ($scheduledAt->isPast()) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['The scheduled publication time must be in the future.'],
            ]);
        }

        return DB::transaction(function () use ($announcement, $scheduledAt, $scheduler) {
            $oldValues = [
                'status' => $announcement->status,
                'scheduled_at' => $announcement->scheduled_at?->toIso8601String(),
                'scheduled_by' => $announcement->scheduled_by,
            ];

            $announcement->update([
                'status' => AgentAnnouncement::STATUS_SCHEDULED,
                'scheduled_at' => $scheduledAt,
                'scheduled_by' => $scheduler->id,
            ]);

            AuditLog::log(
                action: 'agent_announcement_scheduled',
                model: $announcement,
                oldValues: $oldValues,
                newValues: [
                    'status' => AgentAnnouncement::STATUS_SCHEDULED,
                    'scheduled_at' => $scheduledAt->toIso8601String(),
                    'scheduled_by' => $scheduler->id,
                ]
            );

            return $announcement;
        });
    }

    /**
     * Cancel a scheduled announcement and return it to draft.
     */
    public function cancelSchedule(AgentAnnouncement $announcement, User $actor): AgentAnnouncement
    {
        if ($announcement->status !== AgentAnnouncement::STATUS_SCHEDULED) {
            throw ValidationException::withMessages([
                'status' => ['Only scheduled announcements can have their schedule cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($announcement) {
            $oldValues = [
                'status' => $announcement->status,
                'scheduled_at' => $announcement->scheduled_at?->toIso8601String(),
                'scheduled_by' => $announcement->scheduled_by,
            ];

            $announcement->update([
                'status' => AgentAnnouncement::STATUS_DRAFT,
                'scheduled_at' => null,
                'scheduled_by' => null,
            ]);

            AuditLog::log(
                action: 'agent_announcement_schedule_cancelled',
                model: $announcement,
                oldValues: $oldValues,
                newValues: [
                    'status' => AgentAnnouncement::STATUS_DRAFT,
                    'scheduled_at' => null,
                    'scheduled_by' => null,
                ]
            );

            return $announcement;
        });
    }

    /**
     * Manually archive an announcement.
     */
    public function archive(AgentAnnouncement $announcement, User $archiver): AgentAnnouncement
    {
        return DB::transaction(function () use ($announcement, $archiver) {
            $oldStatus = $announcement->status;

            $announcement->update([
                'status' => AgentAnnouncement::STATUS_ARCHIVED,
                'archived_reason' => AgentAnnouncement::ARCHIVED_REASON_MANUAL,
                'archived_by' => $archiver->id,
            ]);

            AuditLog::log(
                action: 'agent_announcement_archived',
                model: $announcement,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => AgentAnnouncement::STATUS_ARCHIVED,
                    'archived_reason' => AgentAnnouncement::ARCHIVED_REASON_MANUAL,
                    'archived_by' => $archiver->id,
                ]
            );

            return $announcement;
        });
    }

    /**
     * Delete a draft announcement.
     */
    public function deleteDraft(AgentAnnouncement $announcement): bool
    {
        if ($announcement->status !== AgentAnnouncement::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft announcements can be deleted.'],
            ]);
        }

        return DB::transaction(function () use ($announcement) {
            AuditLog::log(
                action: 'agent_announcement_deleted',
                model: null,
                oldValues: [
                    'id' => $announcement->id,
                    'title_en' => $announcement->title_en,
                    'status' => $announcement->status,
                ],
                newValues: null
            );

            return $announcement->delete();
        });
    }

    /**
     * Enforce authoritative eligibility for viewing or acknowledging an announcement.
     */
    public function isAgentEligible(User $agent, AgentAnnouncement $announcement): bool
    {
        // 1. Role verification
        if (!$agent->isAgent()) {
            return false;
        }

        // 2. User account status
        $userStatus = $agent->status instanceof UserStatus ? $agent->status->value : (string) $agent->status;
        if ($userStatus !== 'active') {
            return false;
        }

        // 3. Agent profile verification
        if (!$agent->agentProfile || $agent->agentProfile->kyc_status === 'rejected') {
            return false;
        }

        // 4. Announcement publication status and timing
        if ($announcement->status !== AgentAnnouncement::STATUS_PUBLISHED) {
            return false;
        }

        if (!$announcement->published_at || $announcement->published_at->isFuture()) {
            return false;
        }

        // 5. Expiry timing
        if ($announcement->expires_at && $announcement->expires_at->isPast()) {
            return false;
        }

        // 6. Audience matching
        $agentRole = $agent->role instanceof UserRole ? $agent->role->value : (string) $agent->role;
        if ($announcement->target_audience !== AgentAnnouncement::AUDIENCE_ALL && $announcement->target_audience !== $agentRole) {
            return false;
        }

        return true;
    }

    /**
     * Record an announcement read for an agent (idempotent).
     */
    public function recordRead(AgentAnnouncement $announcement, User $agent): AgentAnnouncementRead
    {
        return AgentAnnouncementRead::firstOrCreate(
            [
                'announcement_id' => $announcement->id,
                'agent_id' => $agent->id,
            ],
            [
                'read_at' => now(),
            ]
        );
    }

    /**
     * Get count of unread announcements for an active agent.
     */
    public function getUnreadCountForAgent(User $agent): int
    {
        $agentRole = $agent->role instanceof UserRole ? $agent->role->value : (string) $agent->role;

        return AgentAnnouncement::query()
            ->activeFeed($agentRole)
            ->whereDoesntHave('reads', function (Builder $q) use ($agent) {
                $q->where('agent_id', $agent->id);
            })
            ->count();
    }

    /**
     * Get active feed for an agent with paginated results and read status.
     */
    public function getFeedForAgent(User $agent, int $perPage = 20): LengthAwarePaginator
    {
        $agentRole = $agent->role instanceof UserRole ? $agent->role->value : (string) $agent->role;

        $paginator = AgentAnnouncement::query()
            ->activeFeed($agentRole)
            ->with(['reads' => function ($q) use ($agent) {
                $q->where('agent_id', $agent->id);
            }])
            ->paginate($perPage);

        $paginator->getCollection()->transform(function (AgentAnnouncement $announcement) {
            $announcement->is_read = $announcement->reads->isNotEmpty();
            $announcement->read_at = $announcement->reads->first()?->read_at;
            unset($announcement->reads);
            return $announcement;
        });

        return $paginator;
    }

    /**
     * Get unread urgent announcements for modal acknowledgement.
     */
    public function getUnreadUrgentAnnouncements(User $agent): array
    {
        $agentRole = $agent->role instanceof UserRole ? $agent->role->value : (string) $agent->role;

        return AgentAnnouncement::query()
            ->activeFeed($agentRole)
            ->where('priority', AgentAnnouncement::PRIORITY_URGENT)
            ->whereDoesntHave('reads', function (Builder $q) use ($agent) {
                $q->where('agent_id', $agent->id);
            })
            ->get()
            ->toArray();
    }

    /**
     * Calculate live active network read analytics for an announcement.
     */
    public function getReadAnalytics(AgentAnnouncement $announcement): array
    {
        // 1. Determine eligible active agents query
        $eligibleQuery = User::query()
            ->where('status', 'active')
            ->whereHas('agentProfile', function ($q) {
                $q->where('kyc_status', '!=', 'rejected');
            });

        if ($announcement->target_audience === AgentAnnouncement::AUDIENCE_HLA) {
            $eligibleQuery->where('role', 'high_level_agent');
        } elseif ($announcement->target_audience === AgentAnnouncement::AUDIENCE_LLA) {
            $eligibleQuery->where('role', 'low_level_agent');
        } else {
            $eligibleQuery->whereIn('role', ['high_level_agent', 'low_level_agent']);
        }

        $eligibleAgentsCount = $eligibleQuery->count();

        // 2. Calculate active reads (reads by agents who are currently active and eligible)
        $activeReadsCount = (clone $eligibleQuery)
            ->whereHas('announcementReads', function ($q) use ($announcement) {
                $q->where('announcement_id', $announcement->id);
            })
            ->count();

        // 3. Total all-time reads in table (including deactivated or former agents)
        $totalAllTimeReads = $announcement->reads()->count();

        $percentage = $eligibleAgentsCount > 0
            ? round(($activeReadsCount / $eligibleAgentsCount) * 100, 1)
            : 0.0;

        return [
            'eligible_agents_count' => $eligibleAgentsCount,
            'active_reads_count' => $activeReadsCount,
            'read_percentage' => $percentage,
            'total_all_time_reads' => $totalAllTimeReads,
        ];
    }
}

