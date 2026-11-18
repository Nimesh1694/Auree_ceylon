<?php

namespace App\Console\Commands;

use App\Models\AgentAchievementEvaluationRun;
use App\Services\AgentAchievementService;
use Illuminate\Console\Command;

class BackfillAchievementsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:backfill-achievements
                            {--dry-run : Run in preview mode without creating awards}
                            {--run-id= : ID of an authorized backfill run to execute}
                            {--batch-size=100 : Number of agents to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Preview or execute controlled historical cumulative achievement backfills under Business Owner authorization';

    /**
     * Execute the console command.
     */
    public function handle(AgentAchievementService $achievementService): int
    {
        $runId = $this->option('run-id');
        $batchSize = (int) ($this->option('batch-size') ?: 100);

        // Execution Mode (requires --run-id)
        if ($runId && !$this->option('dry-run')) {
            $run = AgentAchievementEvaluationRun::find($runId);
            if (!$run) {
                $this->error("Evaluation run #{$runId} not found.");
                return 1;
            }

            if ($run->status !== 'authorized') {
                $this->error("Evaluation run #{$runId} cannot be executed. Current status: '{$run->status}'.");
                $this->warn("A Business Owner must authorize this run before execution.");
                return 1;
            }

            $this->info("Executing authorized historical backfill run #{$runId} (batch size: {$batchSize})...");

            try {
                $result = $achievementService->executeBackfillRun($run, null, $batchSize);
                $this->info("Historical backfill completed successfully.");
                $this->table(
                    ['Run ID', 'Status', 'Agents Evaluated', 'Awards Created', 'Awards Corrected', 'Awards Requalified'],
                    [[
                        $result['run_id'],
                        $result['status'],
                        $result['agents_evaluated'],
                        $result['awards_created'],
                        $result['awards_corrected'],
                        $result['awards_requalified'],
                    ]]
                );
                return 0;
            } catch (\Throwable $e) {
                $this->error("Execution error: " . $e->getMessage());
                return 1;
            }
        }

        // Preview / Dry-Run Mode
        $this->info("Generating historical achievement backfill preview snapshot (dry-run)...");

        $run = $achievementService->previewHistoricalBackfill(null);

        $this->info("Preview snapshot successfully generated.");
        $this->table(
            ['Run ID', 'Status', 'Agents Evaluated', 'Prospective Awards', 'Checksum'],
            [[
                $run->id,
                $run->status,
                $run->agents_evaluated,
                $run->awards_created,
                substr($run->metadata['checksum'] ?? '', 0, 16) . '...',
            ]]
        );

        $previewAwards = $run->metadata['preview_awards'] ?? [];
        if (!empty($previewAwards)) {
            $rows = array_map(function ($item) {
                return [
                    $item['agent_id'],
                    $item['agent_name'],
                    $item['achievement_code'],
                    $item['metric_value'],
                    $item['threshold_snapshot'],
                    $item['prospective_action'],
                ];
            }, array_slice($previewAwards, 0, 20));

            $this->table(
                ['Agent ID', 'Agent Name', 'Achievement', 'Metric Value', 'Threshold', 'Prospective Action'],
                $rows
            );

            if (count($previewAwards) > 20) {
                $this->comment("... and " . (count($previewAwards) - 20) . " more prospective awards.");
            }
        } else {
            $this->comment("No agents currently meet new cumulative qualification thresholds.");
        }

        $this->warn("NOTICE: Zero database awards were created. Run #{$run->id} is staged in 'pending_owner_authorization'.");
        $this->line("The Business Owner must authorize this run before it can be executed.");
        $this->line("Once authorized, run: php artisan agent:backfill-achievements --run-id={$run->id}");

        return 0;
    }
}

