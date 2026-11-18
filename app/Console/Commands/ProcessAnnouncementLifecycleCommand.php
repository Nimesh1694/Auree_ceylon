<?php

namespace App\Console\Commands;

use App\Models\AgentAnnouncement;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAnnouncementLifecycleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agents:process-announcement-lifecycle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled agent announcements and expire overdue published broadcasts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $publishedCount = $this->processScheduledPublications();
        $expiredCount = $this->processAutomatedExpirations();

        $this->info("Announcement lifecycle processed: {$publishedCount} published, {$expiredCount} expired.");

        return self::SUCCESS;
    }

    /**
     * Find and publish due scheduled announcements.
     */
    protected function processScheduledPublications(): int
    {
        return DB::transaction(function () {
            $due = AgentAnnouncement::query()
                ->where('status', AgentAnnouncement::STATUS_SCHEDULED)
                ->where('scheduled_at', '<=', now())
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($due as $announcement) {
                $announcement->update([
                    'status' => AgentAnnouncement::STATUS_PUBLISHED,
                    'published_at' => now(),
                    'published_by' => null, // Scheduled publication: runtime executor is CLI scheduler
                ]);

                AuditLog::log(
                    action: 'agent_announcement_published_scheduled',
                    model: $announcement,
                    oldValues: [
                        'status' => AgentAnnouncement::STATUS_SCHEDULED,
                    ],
                    newValues: [
                        'status' => AgentAnnouncement::STATUS_PUBLISHED,
                        'published_at' => now()->toIso8601String(),
                        'published_by' => null,
                        'authorized_by_user_id' => $announcement->scheduled_by,
                        'scheduled_at' => $announcement->scheduled_at?->toIso8601String(),
                        'trigger' => 'scheduler_cron',
                    ]
                );

                $count++;
            }

            return $count;
        });
    }

    /**
     * Find and expire published announcements that have reached their expiration date.
     */
    protected function processAutomatedExpirations(): int
    {
        return DB::transaction(function () {
            $expired = AgentAnnouncement::query()
                ->where('status', AgentAnnouncement::STATUS_PUBLISHED)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($expired as $announcement) {
                $announcement->update([
                    'status' => AgentAnnouncement::STATUS_ARCHIVED,
                    'archived_reason' => AgentAnnouncement::ARCHIVED_REASON_SYSTEM_EXPIRY,
                    'archived_by' => null,
                ]);

                AuditLog::log(
                    action: 'agent_announcement_expired_system',
                    model: $announcement,
                    oldValues: [
                        'status' => AgentAnnouncement::STATUS_PUBLISHED,
                        'expires_at' => $announcement->expires_at?->toIso8601String(),
                    ],
                    newValues: [
                        'status' => AgentAnnouncement::STATUS_ARCHIVED,
                        'archived_reason' => AgentAnnouncement::ARCHIVED_REASON_SYSTEM_EXPIRY,
                        'archived_by' => null,
                        'archived_at' => now()->toIso8601String(),
                        'trigger' => 'scheduler_cron',
                    ]
                );

                $count++;
            }

            return $count;
        });
    }
}

