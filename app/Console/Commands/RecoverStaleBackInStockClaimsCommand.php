<?php

namespace App\Console\Commands;

use App\Services\BackInStockService;
use Illuminate\Console\Command;

class RecoverStaleBackInStockClaimsCommand extends Command
{
    protected $signature = 'back-in-stock:recover-stale-leases {--worker= : Optional custom worker identifier}';

    protected $description = 'Recover expired active subscription leases and reset orphaned processing subscriptions.';

    public function handle(BackInStockService $service): int
    {
        $workerId = $this->option('worker');
        $recovered = $service->recoverStaleLeases($workerId);

        $this->info("Recovered {$recovered} expired lease(s).");

        return self::SUCCESS;
    }
}

