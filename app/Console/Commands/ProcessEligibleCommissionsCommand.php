<?php

namespace App\Console\Commands;

use App\Services\CommissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessEligibleCommissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commissions:process-eligible';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and approve matured pending commissions whose return window has elapsed and COD is reconciled.';

    /**
     * Execute the console command.
     */
    public function handle(CommissionService $commissionService): int
    {
        $this->info('Starting automated maturation processing for eligible commissions...');

        try {
            $approvedCount = $commissionService->approveEligibleCommissions();

            $message = "Automated maturation completed: {$approvedCount} eligible commission entries approved.";
            $this->info($message);
            Log::info("commissions:process-eligible executed: {$approvedCount} approved.");

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to process eligible commissions: {$e->getMessage()}");
            Log::error('commissions:process-eligible failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}

