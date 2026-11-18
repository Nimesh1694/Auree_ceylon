<?php

namespace App\Console\Commands;

use App\Services\AgentAchievementService;
use Illuminate\Console\Command;

class RetryAchievementNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:retry-achievement-notifications {--hours= : Optional lookback window in hours, omit for all history}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit-driven failure recovery engine for idempotent agent achievement notification dispatch';

    /**
     * Execute the console command.
     */
    public function handle(AgentAchievementService $achievementService): int
    {
        $hours = $this->option('hours') ? (int) $this->option('hours') : null;

        $this->info("Scanning achievement audit logs for undelivered notifications" . ($hours ? " (past {$hours} hours)..." : "..."));

        $result = $achievementService->retryFailedNotifications($hours);

        $this->info("Audit log scan completed.");
        $this->table(
            ['Audit Logs Scanned', 'Already Delivered', 'Repaired / Delivered', 'Skipped (Revoked)', 'Skipped (Inactive)', 'Missing Awards'],
            [[
                $result['scanned'],
                $result['existing'],
                $result['repaired'],
                $result['skipped_revoked'],
                $result['skipped_inactive'],
                $result['missing_awards'],
            ]]
        );

        return 0;
    }
}

