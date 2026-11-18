<?php

namespace App\Notifications;

use App\Models\AgentAchievementAward;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

class AgentAchievementReinstatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AgentAchievementAward $award,
        public readonly ?string $reinstatementReason = null,
        ?int $auditLogId = null
    ) {
        $identifier = $auditLogId ? "audit.{$auditLogId}" : "reinstated." . ($award->awarded_at?->timestamp ?? now()->timestamp);
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
        $badgeTier = $achievement?->badge_tier ?? 'bronze';
        $reason = $this->reinstatementReason ?? 'Administrative review';

        return [
            'type' => 'achievement_reinstated',
            'award_id' => $this->award->id,
            'achievement_id' => $this->award->achievement_id,
            'achievement_code' => $achievement?->code,
            'achievement_name' => $name,
            'badge_tier' => $badgeTier,
            'period_key' => $this->award->period_key,
            'title' => "Achievement Reinstated: {$name}!",
            'message' => "Congratulations! Your '{$name}' achievement award has been reinstated. Note: {$reason}",
            'action_url' => '/agent/achievements',
            'awarded_at' => $this->award->awarded_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}

