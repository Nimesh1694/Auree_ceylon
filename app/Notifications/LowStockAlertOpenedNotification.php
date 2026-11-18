<?php

namespace App\Notifications;

use App\Models\StockAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlertOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly StockAlert $alert
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->alert->product;
        $productName = $product?->name_en ?? 'Unknown Product';
        $sku = $product?->sku ?? 'N/A';

        return (new MailMessage)
            ->subject("Low Stock Alert: {$productName} ({$sku})")
            ->greeting('Inventory Low-Stock Alert')
            ->line("Product **{$productName}** (SKU: {$sku}) has reached or fallen below its low-stock threshold.")
            ->line("Current Stock: {$this->alert->current_stock} | Threshold: {$this->alert->threshold_at_trigger}")
            ->line("Trigger Source: " . str_replace('_', ' ', $this->alert->trigger_source))
            ->action('View Low-Stock Alerts', url('/admin/inventory/alerts'));
    }

    public function toArray(object $notifiable): array
    {
        $product = $this->alert->product;

        return [
            'type' => 'low_stock_alert_opened',
            'alert_id' => $this->alert->id,
            'product_id' => $this->alert->product_id,
            'product_name' => $product?->name_en,
            'sku' => $product?->sku,
            'current_stock' => $this->alert->current_stock,
            'threshold' => $this->alert->threshold_at_trigger,
            'trigger_source' => $this->alert->trigger_source,
        ];
    }
}

