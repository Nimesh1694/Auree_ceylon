<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LowStockDailyDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly Collection $openAlerts
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->openAlerts->count();
        $mail = (new MailMessage)
            ->subject("Daily Low-Stock Digest ({$count} Open Alert" . ($count === 1 ? '' : 's') . ')')
            ->greeting('Daily Inventory Low-Stock Digest')
            ->line("There are currently {$count} unacknowledged low-stock alert(s) requiring attention:");

        foreach ($this->openAlerts->take(15) as $alert) {
            $name = $alert->product?->name_en ?? 'Unknown';
            $sku = $alert->product?->sku ?? 'N/A';
            $mail->line("- {$name} ({$sku}): {$alert->current_stock} left (Threshold: {$alert->threshold_at_trigger})");
        }

        return $mail->action('Manage Low-Stock Alerts', url('/admin/inventory/alerts'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_stock_daily_digest',
            'open_alerts_count' => $this->openAlerts->count(),
            'alert_ids' => $this->openAlerts->pluck('id')->values()->all(),
        ];
    }
}

