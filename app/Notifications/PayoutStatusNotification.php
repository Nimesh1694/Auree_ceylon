<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayoutStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly PayoutRequest $payoutRequest,
        public readonly string $eventType,
        public readonly ?string $remarks = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $amountFormatted = number_format((float) $this->payoutRequest->amount, 2);

        $titles = [
            'requested' => 'Payout Request Submitted',
            'approved' => 'Payout Request Approved',
            'rejected' => 'Payout Request Rejected',
            'processing' => 'Payout Processing Started',
            'paid' => 'Payout Completed',
            'failed' => 'Payout Transfer Failed',
            'cancelled' => 'Payout Request Cancelled',
        ];

        $title = $titles[$this->eventType] ?? 'Payout Status Updated';

        $messages = [
            'requested' => "Your payout request #{$this->payoutRequest->reference_number} for LKR {$amountFormatted} has been submitted for Owner approval.",
            'approved' => "Your payout request #{$this->payoutRequest->reference_number} for LKR {$amountFormatted} has been approved by the company Owner.",
            'rejected' => "Your payout request #{$this->payoutRequest->reference_number} for LKR {$amountFormatted} was rejected." . ($this->remarks ? " Reason: {$this->remarks}" : ''),
            'processing' => "Bank transfer processing has initiated for payout request #{$this->payoutRequest->reference_number}.",
            'paid' => "Funds of LKR {$amountFormatted} have been transferred to your registered bank account for payout #{$this->payoutRequest->reference_number}.",
            'failed' => "The bank transfer for payout #{$this->payoutRequest->reference_number} could not be cleared by the bank." . ($this->remarks ? " Details: {$this->remarks}" : '') . " Reserved funds have been released back to your available balance.",
            'cancelled' => "Your payout request #{$this->payoutRequest->reference_number} has been cancelled and reserved funds released.",
        ];

        $message = $messages[$this->eventType] ?? "Your payout request status is now {$this->payoutRequest->status}.";

        return [
            'title' => $title,
            'message' => $message,
            'payout_reference' => $this->payoutRequest->reference_number,
            'amount' => (float) $this->payoutRequest->amount,
            'status' => $this->payoutRequest->status,
            'action_url' => '/agent/wallet',
            'created_at' => now()->toIso8601String(),
        ];
    }
}

