<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgentKycUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $kycStatus,
        public readonly ?string $notes = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'KYC Verification Status Updated',
            'message' => "Your KYC verification status has been marked as {$this->kycStatus}." . ($this->notes ? " Remarks: {$this->notes}" : ''),
            'kyc_status' => $this->kycStatus,
            'action_url' => '/agent/profile',
            'created_at' => now()->toIso8601String(),
        ];
    }
}

