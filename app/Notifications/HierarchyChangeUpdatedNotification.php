<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HierarchyChangeUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $status,
        public readonly ?string $notes = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Hierarchy Move Request ' . ucfirst($this->status),
            'message' => "Your network hierarchy position update has been {$this->status} by executive management." . ($this->notes ? " Notes: {$this->notes}" : ''),
            'status' => $this->status,
            'action_url' => '/agent/team',
            'created_at' => now()->toIso8601String(),
        ];
    }
}

