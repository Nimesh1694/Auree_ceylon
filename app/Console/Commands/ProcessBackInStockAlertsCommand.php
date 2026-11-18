<?php

namespace App\Console\Commands;

use App\Services\BackInStockService;
use Illuminate\Console\Command;

class ProcessBackInStockAlertsCommand extends Command
{
    protected $signature = 'back-in-stock:process-alerts {--worker= : Optional custom worker identifier}';

    protected $description = 'Process active back-in-stock restock events, claiming pending subscribers and dispatching alert notifications.';

    public function handle(BackInStockService $service): int
    {
        $workerId = $this->option('worker');
        $stats = $service->processActiveRestockEvents($workerId);

        $this->info("Processed {$stats['events_processed']} event(s), allocated {$stats['claims_allocated']} claim(s).");

        return self::SUCCESS;
    }
}

