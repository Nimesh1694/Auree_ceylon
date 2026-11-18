<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AgentSalesTargetService;
use Illuminate\Console\Command;
use Throwable;

class RolloverTargetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:rollover-targets 
                            {source_month : Source month in YYYY-MM format} 
                            {target_month : Target month in YYYY-MM format} 
                            {--actor-id= : ID of the management user executing the rollover} 
                            {--batch-size=100 : Batch size for processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollover monthly agent sales targets from a source month to a target month in bounded batches';

    /**
     * Execute the console command.
     */
    public function handle(AgentSalesTargetService $targetService): int
    {
        $sourceMonth = (string) $this->argument('source_month');
        $targetMonth = (string) $this->argument('target_month');
        $batchSize = (int) $this->option('batch-size') ?: 100;

        $actorId = $this->option('actor-id');
        if ($actorId) {
            $actor = User::find($actorId);
        } else {
            // Find default active Business Owner or Super Admin for CLI execution
            $actor = User::whereIn('role', ['owner', 'super_admin'])
                ->where('status', 'active')
                ->orderBy('id', 'asc')
                ->first();
        }

        if (!$actor) {
            $this->error("No active management actor found for executing target rollover.");
            return Command::FAILURE;
        }

        $this->info("Initiating bulk target rollover from {$sourceMonth} to {$targetMonth} (Acting Manager: {$actor->name} #{$actor->id})...");

        try {
            $summary = $targetService->rolloverMonthlyTargets(
                $sourceMonth,
                $targetMonth,
                $actor,
                $batchSize
            );

            $this->info("Bulk rollover successfully executed.");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Source Targets Processed', $summary['processed']],
                    ['New Targets Created', $summary['created']],
                    ['Skipped Existing Targets', $summary['skipped_existing']],
                    ['Skipped Inactive / KYC-Rejected Agents', $summary['skipped_inactive']],
                ]
            );

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Bulk rollover failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
