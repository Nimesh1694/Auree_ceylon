<?php

namespace App\Console\Commands;

use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AgentTargetAssignedNotification;
use App\Notifications\AgentTargetUpdatedNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Throwable;

class RetryTargetNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:retry-target-notifications {--hours= : Optional lookback window in hours, omit for all history}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit-driven failure recovery engine for idempotent agent target notification dispatch';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $actions = [
            'agent_sales_target_created',
            'agent_sales_target_updated',
            'agent_sales_target_hla_assigned',
            'agent_sales_target_hla_updated',
            'agent_sales_target_historical_created',
            'agent_sales_target_historical_override',
            'agent_sales_target_rolled_over',
        ];

        $query = AuditLog::query()
            ->where('auditable_type', AgentSalesTarget::class)
            ->whereIn('action', $actions)
            ->orderBy('id', 'asc');

        if ($this->option('hours')) {
            $hours = (int) $this->option('hours');
            if ($hours > 0) {
                $query->where('created_at', '>=', Carbon::now()->subHours($hours));
            }
        }

        $scannedCount = 0;
        $existingCount = 0;
        $repairedCount = 0;
        $skippedInactiveCount = 0;
        $missingTargetCount = 0;

        $query->chunk(100, function ($auditLogs) use (
            &$scannedCount,
            &$existingCount,
            &$repairedCount,
            &$skippedInactiveCount,
            &$missingTargetCount
        ) {
            foreach ($auditLogs as $auditLog) {
                $scannedCount++;

                // 1. Reconstruct deterministic UUIDv5 ID
                $deterministicId = Uuid::uuid5(
                    Uuid::NAMESPACE_DNS,
                    "auraluxe.target.audit.{$auditLog->id}"
                )->toString();

                // 2. Check if notification already delivered
                if (DB::table('notifications')->where('id', $deterministicId)->exists()) {
                    $existingCount++;
                    continue;
                }

                // 3. Resolve target
                /** @var AgentSalesTarget|null $target */
                $target = AgentSalesTarget::find($auditLog->auditable_id);
                if (!$target) {
                    $missingTargetCount++;
                    $this->warn("AuditLog #{$auditLog->id}: Target record #{$auditLog->auditable_id} missing; notification skipped.");
                    continue;
                }

                // 4. Resolve recipient
                /** @var User|null $recipient */
                $recipient = User::find($target->agent_id);
                if (!$recipient || !$recipient->isAgent() || ((string) ($recipient->status->value ?? $recipient->status)) !== 'active') {
                    $skippedInactiveCount++;
                    $this->warn("AuditLog #{$auditLog->id}: Recipient #{$target->agent_id} is inactive or invalid; notification skipped.");
                    continue;
                }
                if ($recipient->agentProfile && $recipient->agentProfile->kyc_status === 'rejected') {
                    $skippedInactiveCount++;
                    $this->warn("AuditLog #{$auditLog->id}: Recipient #{$target->agent_id} KYC is rejected; notification skipped.");
                    continue;
                }

                // 5. Resolve actor
                $actor = User::find($auditLog->user_id) ?? User::find($target->created_by) ?? $recipient;

                // 6. Instantiate notification with deterministic UUID
                $action = $auditLog->action;
                $notificationClass = match ($action) {
                    'agent_sales_target_updated',
                    'agent_sales_target_hla_updated',
                    'agent_sales_target_historical_override' => AgentTargetUpdatedNotification::class,
                    default => AgentTargetAssignedNotification::class,
                };

                $notification = new $notificationClass($target, $actor, $auditLog);

                try {
                    $recipient->notify($notification);
                    $repairedCount++;
                } catch (Throwable $e) {
                    if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() == 1062) {
                        $existingCount++;
                    } else {
                        $this->error("Failed to repair notification for AuditLog #{$auditLog->id}: " . $e->getMessage());
                    }
                }
            }
        });

        $this->info("Agent Target Notification Failure Recovery Completed.");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Audit Logs Scanned', $scannedCount],
                ['Already Delivered (Skipped)', $existingCount],
                ['Missing / Repaired Notifications', $repairedCount],
                ['Skipped Inactive / KYC-Rejected', $skippedInactiveCount],
                ['Missing Target Records', $missingTargetCount],
            ]
        );

        return Command::SUCCESS;
    }
}

