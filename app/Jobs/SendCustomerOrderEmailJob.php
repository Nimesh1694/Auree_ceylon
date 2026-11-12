<?php

namespace App\Jobs;

use App\Models\OrderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCustomerOrderEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $orderNotificationId,
        public readonly Mailable $mailable
    ) {
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $notification = OrderNotification::find($this->orderNotificationId);

        if (! $notification) {
            Log::warning('SendCustomerOrderEmailJob: OrderNotification record not found.', [
                'order_notification_id' => $this->orderNotificationId,
            ]);
            return;
        }

        // Idempotency: skip if already sent
        if ($notification->isSent()) {
            Log::info('SendCustomerOrderEmailJob: Notification already sent, suppressing duplicate.', [
                'order_notification_id' => $notification->id,
                'order_number' => $notification->order_number,
                'notification_type' => $notification->notification_type,
            ]);
            return;
        }

        // Check if recipient email is valid
        if (empty($notification->recipient_email) || ! filter_var($notification->recipient_email, FILTER_VALIDATE_EMAIL)) {
            $notification->update([
                'status' => OrderNotification::STATUS_SKIPPED,
                'error_details' => 'Recipient email missing or invalid format: ' . ($notification->recipient_email ?? 'null'),
            ]);
            return;
        }

        $notification->increment('attempts');

        try {
            Mail::to($notification->recipient_email)->send($this->mailable);

            $notification->update([
                'status' => OrderNotification::STATUS_SENT,
                'sent_at' => now(),
                'mailer' => config('mail.default'),
                'error_details' => null,
            ]);

            Log::info('SendCustomerOrderEmailJob: Order notification email sent successfully.', [
                'order_notification_id' => $notification->id,
                'order_number' => $notification->order_number,
                'recipient' => $notification->recipient_email,
                'type' => $notification->notification_type,
            ]);
        } catch (Throwable $e) {
            $notification->update([
                'status' => OrderNotification::STATUS_FAILED,
                'error_details' => substr($e->getMessage(), 0, 1000),
            ]);

            Log::error('SendCustomerOrderEmailJob: Failed delivering order notification email.', [
                'order_notification_id' => $notification->id,
                'order_number' => $notification->order_number,
                'attempts' => $notification->attempts,
                'error' => $e->getMessage(),
            ]);

            // Re-throw so Laravel queue manages retries according to $tries & $backoff
            throw $e;
        }
    }

    /**
     * Handle a job failure after exhausting all attempts.
     */
    public function failed(?Throwable $exception): void
    {
        $notification = OrderNotification::find($this->orderNotificationId);

        if ($notification && ! $notification->isSent()) {
            $notification->update([
                'status' => OrderNotification::STATUS_FAILED,
                'error_details' => 'Exhausted retry attempts (' . $this->tries . '): ' . substr($exception?->getMessage() ?? 'Unknown error', 0, 1000),
            ]);
        }

        Log::critical('SendCustomerOrderEmailJob: Permanent failure delivering email after exhausting retries.', [
            'order_notification_id' => $this->orderNotificationId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
