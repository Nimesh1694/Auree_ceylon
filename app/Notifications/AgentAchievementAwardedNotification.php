<?php

namespace App\Notifications;

use App\Models\AgentAchievementAward;
use App\Services\AgentAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

class AgentAchievementAwardedNotification extends Notification
{
    use Queueable;

    public string $event;

    public function __construct(
        public readonly AgentAchievementAward $award,
        ?string $event = null,
        public readonly ?string $customMessage = null,
        ?int $auditLogId = null
    ) {
        $this->event = $event ?? ($award->metadata['event_type'] ?? 'earned');

        // Deterministic UUIDv5 primary key based on award id, event, and immutable audit log ID when available
        $identifier = $auditLogId ? "audit.{$auditLogId}" : $this->event;
        $this->id = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            "auraluxe.achievement.award.{$award->id}.{$identifier}"
        )->toString();
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
        $achievement = $this->award->achievement;
        $name = $achievement?->name ?? 'Achievement';
        $badgeTier = $achievement?->badge_tier ?? 'bronze';
        $badgeIcon = $achievement?->badge_icon ?? 'award';
        $periodKey = $this->award->period_key;

        $progressFormatted = $this->formatMetricValue($achievement?->metric_type, (string) $this->award->achieved_value);
        $thresholdFormatted = $this->formatMetricValue($achievement?->metric_type, (string) $this->award->threshold_snapshot);

        $title = match ($this->event) {
            'corrected' => "Achievement Status Updated: {$name}",
            'requalified' => "Achievement Restored: {$name}!",
            'monthly_target' => "Target Attainment Unlocked: {$name}!",
            'streak' => "Streak Milestone Unlocked: {$name}!",
            default => "Achievement Unlocked: {$name}!",
        };

        $message = $this->customMessage ?? match ($this->event) {
            'corrected' => "Your achievement '{$name}' was adjusted to corrected status due to customer returns/refunds.",
            'requalified' => "Congratulations! Subsequent sales have qualified you again for '{$name}' ({$badgeTier} tier).",
            'monthly_target' => "Congratulations! You achieved {$progressFormatted} of your sales target for {$periodKey} and earned the '{$name}' badge.",
            'streak' => "Incredible consistency! You have maintained a {$progressFormatted} target achievement streak and unlocked '{$name}'.",
            default => "Congratulations! You have earned the '{$name}' {$badgeTier} badge.",
        };

        return [
            'title' => $title,
            'message' => $message,
            'award_id' => $this->award->id,
            'achievement_id' => $this->award->achievement_id,
            'achievement_code' => $achievement?->code,
            'achievement_name' => $name,
            'badge_tier' => $badgeTier,
            'badge_icon' => $badgeIcon,
            'period_key' => $periodKey,
            'status' => $this->award->status,
            'event' => $this->event,
            'progress_formatted' => $progressFormatted,
            'threshold_formatted' => $thresholdFormatted,
            'achieved_value' => (string) $this->award->achieved_value,
            'threshold_snapshot' => (string) $this->award->threshold_snapshot,
            'action_url' => '/agent/achievements',
            'awarded_at' => $this->award->awarded_at?->toIso8601String() ?? now()->toIso8601String(),
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Format metric values for user celebration presentation.
     */
    protected function formatMetricValue(?string $metricType, string $value): string
    {
        return match ($metricType) {
            'target_attainment_pct' => number_format((float) $value, 2, '.', '') . '%',
            'streak_months' => ((int) (float) $value) . ' Month' . (((int) (float) $value) === 1 ? '' : 's'),
            'order_count', 'customer_count', 'active_recruits' => (string) ((int) (float) $value),
            default => AgentAnalyticsService::formatExactLkr($value),
        };
    }
}

