<?php

namespace App\Jobs;

use App\Mail\CustomerInquiryStaffMail;
use App\Models\CustomerInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCustomerInquiryEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Strictly authorized support notification recipient address.
     */
    public const AUTHORIZED_RECIPIENT = 'care@auraluxe.lk';

    /**
     * The ID of the customer inquiry to notify staff about.
     */
    public int $inquiryId;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * The maximum number of unhandled exceptions allowed before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Immutable UNIX timestamp representing the 20-minute retry deadline.
     */
    public ?int $retryUntilTimestamp = null;

    /**
     * Create a new job instance.
     */
    public function __construct(int $inquiryId)
    {
        $this->inquiryId = $inquiryId;
        $this->afterCommit = true;
        $this->retryUntilTimestamp = now()->addMinutes(20)->getTimestamp();
    }

    /**
     * Determine the time at which the job should timeout/expire.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return Carbon::createFromTimestamp(
            $this->retryUntilTimestamp ?? now()->addMinutes(20)->getTimestamp()
        );
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Classify and sanitize an exception into a safe, length-limited string.
     * Prevents leakage of SQL queries, credentials, file paths, and network banners.
     */
    public function sanitizeException(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException) {
            return 'Database service temporarily unavailable.';
        }

        if ($e instanceof \Symfony\Component\Mailer\Exception\TransportExceptionInterface) {
            return 'SMTP transport delivery failed.';
        }

        if ($e instanceof \InvalidArgumentException) {
            return 'Support email configuration is invalid or missing.';
        }

        $className = class_basename($e);
        return substr("Processing error encountered ({$className}).", 0, 255);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Pre-claim inquiry inspection (inside sanitized exception boundary)
        try {
            $inquiry = CustomerInquiry::find($this->inquiryId);
        } catch (\Throwable $e) {
            Log::warning('Customer inquiry pre-claim lookup failed.', [
                'inquiry_id' => $this->inquiryId,
                'reason' => $this->sanitizeException($e),
            ]);
            $this->release(60);
            return;
        }

        // 2. Missing record handling: gracefully complete without retrying
        if (! $inquiry) {
            Log::warning('Customer inquiry notification skipped: Record not found.', [
                'inquiry_id' => $this->inquiryId,
            ]);
            return;
        }

        // 3. Terminal state inspection: do not re-send if already sent or skipped
        if (in_array($inquiry->notification_status, ['sent', 'skipped'], true)) {
            Log::info('Customer inquiry notification already processed.', [
                'inquiry_id' => $this->inquiryId,
                'status' => $inquiry->notification_status,
            ]);
            return;
        }

        // 4. Active lease detection: release back to queue if another worker holds an active lease (<= 15 minutes old)
        if ($inquiry->notification_status === 'processing' && $inquiry->updated_at && $inquiry->updated_at->gt(now()->subMinutes(15))) {
            Log::info('Customer inquiry notification lease currently active on another worker. Releasing for retry.', [
                'inquiry_id' => $this->inquiryId,
                'lease_updated_at' => $inquiry->updated_at->toIso8601String(),
            ]);
            $this->release(30);
            return;
        }

        // 5. Atomic Lease Claim: claim pending, failed, or stale processing leases (> 15 minutes old)
        try {
            $claimed = CustomerInquiry::where('id', $this->inquiryId)
                ->where(function ($query) {
                    $query->whereIn('notification_status', ['pending', 'failed'])
                          ->orWhere(function ($staleQuery) {
                              $staleQuery->where('notification_status', 'processing')
                                         ->where('updated_at', '<=', now()->subMinutes(15));
                          });
                })
                ->update([
                    'notification_status' => 'processing',
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Customer inquiry atomic lease claim failed due to database connectivity issue.', [
                'inquiry_id' => $this->inquiryId,
                'reason' => $this->sanitizeException($e),
            ]);
            $this->release(60);
            return;
        }

        // 6. Lost atomic claim race: another concurrent worker claimed the lease
        if ($claimed === 0) {
            Log::info('Customer inquiry atomic claim lost to concurrent worker. Releasing.', [
                'inquiry_id' => $this->inquiryId,
            ]);
            $this->release(30);
            return;
        }

        // 7. Post-claim delivery execution under acquired lease
        try {
            // Strictly enforce the authorized support notification recipient (care@auraluxe.lk)
            $recipient = self::AUTHORIZED_RECIPIENT;

            $freshInquiry = CustomerInquiry::find($this->inquiryId);
            if (! $freshInquiry) {
                return;
            }

            Mail::to($recipient)->send(new CustomerInquiryStaffMail($freshInquiry));

            CustomerInquiry::where('id', $this->inquiryId)->update([
                'notification_status' => 'sent',
                'notification_error' => null,
                'updated_at' => now(),
            ]);

            Log::info('Customer inquiry staff notification successfully delivered.', [
                'inquiry_id' => $this->inquiryId,
                'recipient' => $recipient,
            ]);
        } catch (\Throwable $e) {
            $sanitized = $this->sanitizeException($e);

            try {
                CustomerInquiry::where('id', $this->inquiryId)->update([
                    'notification_status' => 'failed',
                    'notification_error' => substr($sanitized, 0, 500),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $secondaryDbError) {
                Log::error('Customer inquiry failed to persist error telemetry.', [
                    'inquiry_id' => $this->inquiryId,
                    'reason' => 'Secondary database error during status update.',
                ]);
            }

            Log::error('Customer inquiry email delivery failed.', [
                'inquiry_id' => $this->inquiryId,
                'reason' => $sanitized,
            ]);

            // Re-throw without attaching $e as previous to prevent exception chain leakage
            throw new \RuntimeException($sanitized);
        }
    }

    /**
     * Handle permanent job failure when all attempts or deadline are exhausted.
     */
    public function failed(?\Throwable $e): void
    {
        $sanitized = $e ? $this->sanitizeException($e) : 'Exhausted maximum retry duration.';

        try {
            CustomerInquiry::where('id', $this->inquiryId)->update([
                'notification_status' => 'failed',
                'notification_error' => substr(sprintf('Permanent failure after %d attempts: %s', $this->attempts(), $sanitized), 0, 500),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $dbException) {
            Log::critical('Customer inquiry failed() hook could not persist final status.', [
                'inquiry_id' => $this->inquiryId,
                'reason' => 'Database unavailable during permanent failure recording.',
            ]);
        }
    }
}
