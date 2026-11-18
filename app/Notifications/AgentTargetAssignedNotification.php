<?php

namespace App\Notifications;

use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AgentAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

class AgentTargetAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AgentSalesTarget $target,
        public readonly User $actor,
        public readonly AuditLog $auditLog,
        public readonly ?string $action = null,
        public readonly ?string $reason = null
    ) {
        // Enforce deterministic UUIDv5 primary key based on the underlying audit log id
        $this->id = Uuid::uuid5(Uuid::NAMESPACE_DNS, "auraluxe.target.audit.{$auditLog->id}")->toString();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $formattedAmount = AgentAnalyticsService::formatExactLkr($this->target->sales_target);
        $actorRole = $this->actor->role?->value ?? (string) $this->actor->role;
        $action = $this->action ?? $this->auditLog->action;
        $reason = $this->reason ?? ($this->auditLog->new_values['reason'] ?? $this->auditLog->new_values['override_reason'] ?? null);

        $title = match ($action) {
            'agent_sales_target_hla_assigned' => 'Downline Sales Target Assigned',
            'agent_sales_target_historical_created' => 'Historical Sales Target Established',
            'agent_sales_target_rolled_over' => 'Sales Target Rolled Over',
            default => 'Monthly Sales Target Assigned',
        };

        $message = match ($action) {
            'agent_sales_target_hla_assigned' => "Your upline manager {$this->actor->name} has assigned your monthly sales target of {$formattedAmount} for {$this->target->target_month}.",
            'agent_sales_target_historical_created' => "A historical sales target of {$formattedAmount} was established for {$this->target->target_month} by Business Owner {$this->actor->name}.",
            'agent_sales_target_rolled_over' => "Your monthly sales target of {$formattedAmount} has been rolled over for {$this->target->target_month}.",
            default => "A new monthly sales target of {$formattedAmount} has been assigned for {$this->target->target_month}.",
        };

        return [
            'title' => $title,
            'message' => $message,
            'target_id' => $this->target->id,
            'target_month' => $this->target->target_month,
            'sales_target' => (string) $this->target->sales_target,
            'sales_target_formatted' => $formattedAmount,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_role' => $actorRole,
            'action' => $action,
            'reason' => $reason,
            'action_url' => '/agent/analytics',
            'audit_log_id' => $this->auditLog->id,
            'created_at' => now()->toIso8601String(),
        ];
    }
}

