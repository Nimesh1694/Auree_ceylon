<?php

namespace App\Notifications;

use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AgentAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

class AgentTargetUpdatedNotification extends Notification
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
            'agent_sales_target_hla_updated' => 'Downline Sales Target Updated',
            'agent_sales_target_historical_override' => 'Historical Sales Target Overridden',
            default => 'Monthly Sales Target Updated',
        };

        $message = match ($action) {
            'agent_sales_target_hla_updated' => "Your upline manager {$this->actor->name} has updated your sales target for {$this->target->target_month} to {$formattedAmount}.",
            'agent_sales_target_historical_override' => "Your historical sales target for {$this->target->target_month} was overridden to {$formattedAmount} by Business Owner {$this->actor->name}.",
            default => "Your monthly sales target for {$this->target->target_month} has been updated to {$formattedAmount}.",
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

