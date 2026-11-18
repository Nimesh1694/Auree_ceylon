<?php

namespace App\Notifications;

use App\Models\AgentAchievementAward;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

class AgentAchievementRevokedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AgentAchievementAward $award,
        public readonly ?string $revocationReason = null,
        ?int $auditLogId = null
    ) {
        $identifier = $auditLogId ? "audit.{$auditLogId}" : "revoked." . ($award->revoked_at?->timestamp ?? now()->timestamp);
        $this->id = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.achievement.award.{$award->id}.{$identifier}"
        )->toString();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $achievement = $this->award->achievement;
        $name = $achievement?->name ?? 'Achievement';
        $reason = $this->revocationReason ?? $this->award->revocation_reason ?? 'Administrative decision';

        return [
            'type' => 'achievement_revoked',
            'award_id' => $this->award->id,
            'achievement_id' => $this->award->achievement_id,
            'achievement_code' => $achievement?->code,
            'achievement_name' => $name,
            'badge_tier' => $achievement?->badge_tier ?? 'bronze',
            'period_key' => $this->award->period_key,
            'title' => "Achievement Revoked: {$name}",
            'message' => "Your '{$name}' achievement award has been revoked. Reason: {$reason}",
            'action_url' => '/agent/achievements',
            'revoked_at' => $this->award->revoked_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}

