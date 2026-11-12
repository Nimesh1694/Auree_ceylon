<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgentStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $newStatus,
        public readonly ?string $notes = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Agent Account Status Updated',
            'message' => "Your agent account status has been updated to {$this->newStatus}." . ($this->notes ? " Note: {$this->notes}" : ''),
            'status' => $this->newStatus,
            'action_url' => '/agent/dashboard',
            'created_at' => now()->toIso8601String(),
        ];
    }
}

