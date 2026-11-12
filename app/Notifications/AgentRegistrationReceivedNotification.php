<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgentRegistrationReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $agentName,
        public readonly string $referralCode
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Agent Application Received',
            'message' => "Application for {$this->agentName} (Code: {$this->referralCode}) has been submitted for management approval.",
            'action_url' => '/admin/agents',
            'created_at' => now()->toIso8601String(),
        ];
    }
}

