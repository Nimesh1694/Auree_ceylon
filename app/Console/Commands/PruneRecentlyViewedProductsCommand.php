<?php

namespace App\Console\Commands;

use App\Services\RecentlyViewedProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PruneRecentlyViewedProductsCommand extends Command
{
    protected $signature = 'aura:prune-recently-viewed
                            {--days=60 : Retention period in days (must be >= 1)}';

    protected $description = 'Prune expired recently viewed records and aged merge records.';

    public function handle(RecentlyViewedProductService $service): int
    {
        $daysInput = $this->option('days');
        if (!preg_match('/^\d+$/', (string) $daysInput) || (int) $daysInput < 1) {
            $this->error('--days must be a positive integer (minimum 1).');
            return self::INVALID; // exit code 2
        }
        $days = (int) $daysInput;

        $this->info("Pruning view records older than {$days} days...");

        try {
            $prunedViews = $service->pruneExpiredViewRecords($days);
            $this->info("Pruned {$prunedViews} view record(s).");
        } catch (Throwable $e) {
            // CR-34: Safe diagnostic logging — no raw SQL, no exception message dumps
            $this->error('Failed pruning view records. Diagnostic code: ERR_PRUNE_VIEWS_FAILED.');
            Log::channel('daily')->error('Pruning view records failed', [
                'diagnostic_code' => 'ERR_PRUNE_VIEWS_FAILED',
                'error_class'     => get_class($e),
                'error_code'      => $e->getCode(),
            ]);
            return self::FAILURE; // exit code 1
        }

        $this->info('Pruning merge records older than 7 days...');

        try {
            $prunedMerge = $service->pruneExpiredMergeRecords();
            $this->info("Pruned {$prunedMerge} merge record(s).");
        } catch (Throwable $e) {
            // CR-34: Safe diagnostic logging
            $this->error('Failed pruning merge records. Diagnostic code: ERR_PRUNE_MERGE_FAILED.');
            Log::channel('daily')->error('Pruning merge records failed', [
                'diagnostic_code' => 'ERR_PRUNE_MERGE_FAILED',
                'error_class'     => get_class($e),
                'error_code'      => $e->getCode(),
            ]);
            return self::FAILURE; // exit code 1
        }

        $this->info('Pruning complete.');
        return self::SUCCESS; // exit code 0
    }
}

