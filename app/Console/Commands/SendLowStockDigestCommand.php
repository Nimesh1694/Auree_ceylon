<?php

namespace App\Console\Commands;

use App\Services\LowStockAlertService;
use Illuminate\Console\Command;

class SendLowStockDigestCommand extends Command
{
    protected $signature = 'inventory:send-low-stock-digest';

    protected $description = 'Send daily digest of open unacknowledged low-stock alerts to authorized inventory staff.';

    public function handle(LowStockAlertService $alertService): int
    {
        $count = $alertService->sendDailyDigest();
        $this->info("Low-stock digest processed for {$count} open alert(s).");

        return self::SUCCESS;
    }
}

