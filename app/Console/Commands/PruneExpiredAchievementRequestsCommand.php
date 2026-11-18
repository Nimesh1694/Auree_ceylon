<?php

namespace App\Console\Commands;

use App\Services\AgentAchievementService;
use Illuminate\Console\Command;

class PruneExpiredAchievementRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:prune-expired-achievement-requests';

    /**
     * The console command description.
     */
    protected $description = 'Prune overdue pending discretionary achievement requests older than 30 days';

    /**
     * Execute the console command.
     */
    public function handle(AgentAchievementService $service): int
    {
        $this->info("Scanning for expired discretionary achievement requests...");

        $count = $service->pruneExpiredRequests();

        $this->info("Successfully expired {$count} overdue achievement nomination(s).");

        return self::SUCCESS;
    }
}

