<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AgentAchievementService;
use App\Services\PeriodBoundaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EvaluateMonthlyAchievementsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:evaluate-monthly-achievements
                            {month? : Target month to evaluate in YYYY-MM format (defaults to previous month)}
                            {--agent-id= : Specific agent ID to evaluate}
                            {--batch-size=100 : Number of agents to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate monthly sales target achievements and streaks across active agents';

    /**
     * Execute the console command.
     */
    public function handle(AgentAchievementService $achievementService): int
    {
        $month = $this->argument('month') ?? Carbon::now(PeriodBoundaryService::TIMEZONE)->subMonthNoOverflow()->format('Y-m');
        $batchSize = (int) ($this->option('batch-size') ?: 100);
        $agentId = $this->option('agent-id');

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error("Invalid month format: '{$month}'. Expected format is YYYY-MM.");
            return 1;
        }

        // Single Agent Evaluation Mode
        if ($agentId) {
            $agent = User::find($agentId);
            if (!$agent) {
                $this->error("Agent with ID #{$agentId} not found.");
                return 1;
            }

            $this->info("Evaluating monthly achievements for agent #{$agent->id} ({$agent->name}) for {$month}...");

            $result = $achievementService->evaluateMonthlyTargetAchievementsForAgent($agent, $month);

            if (!$result['eligible']) {
                $this->warn("Agent is not eligible for achievements (must be active HLA/LLA with verified KYC).");
                return 0;
            }

            if (!$result['has_target']) {
                $this->warn("Agent does not have an active positive sales target assigned for {$month}.");
                return 0;
            }

            $this->info("Evaluation completed successfully.");
            $this->table(
                ['Agent ID', 'Month', 'Target (LKR)', 'Net Sales (LKR)', 'Attainment %', 'New Awards', 'Corrected', 'Requalified', 'Current Streak'],
                [[
                    $agent->id,
                    $month,
                    $result['target_amount'],
                    $result['net_sales'],
                    $result['attainment_pct'] . '%',
                    $result['awarded'],
                    $result['corrected'],
                    $result['requalified'],
                    $result['streak_months'] . ' Months',
                ]]
            );

            return 0;
        }

        // Batch Evaluation Mode across all eligible agents
        $this->info("Evaluating monthly achievements for month {$month} in batches of {$batchSize}...");

        $result = $achievementService->evaluateMonthlyTargetAchievementsBatch($month, null, $batchSize);

        $this->info("Monthly evaluation completed successfully.");
        $this->table(
            ['Run ID', 'Month', 'Status', 'Agents Evaluated', 'Awards Created', 'Awards Corrected', 'Awards Requalified', 'Streaks Active'],
            [[
                $result['run_id'],
                $month,
                $result['status'],
                $result['agents_evaluated'],
                $result['awards_created'],
                $result['awards_corrected'],
                $result['awards_requalified'],
                $result['streaks_updated'],
            ]]
        );

        return 0;
    }
}

