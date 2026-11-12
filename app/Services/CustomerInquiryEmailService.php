<?php

namespace App\Services;

use App\Jobs\SendCustomerInquiryEmailJob;
use App\Models\CustomerInquiry;
use Illuminate\Support\Facades\Log;

class CustomerInquiryEmailService
{
    /**
     * Dispatch the staff email notification job for a customer inquiry.
     *
     * Ensures dispatch-level safety:
     * - Returns false without queueing if already sent.
     * - Catches queue connection/dispatch failures to prevent rolling back inquiry creation.
     */
    public function dispatchStaffNotification(CustomerInquiry $inquiry): bool
    {
        // Guard against duplicate dispatch if already in terminal 'sent' state
        if ($inquiry->notification_status === 'sent') {
            Log::info('Customer inquiry notification dispatch skipped: already sent.', [
                'inquiry_id' => $inquiry->id,
            ]);
            return false;
        }

        try {
            SendCustomerInquiryEmailJob::dispatch($inquiry->id);
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch customer inquiry notification job to queue.', [
                'inquiry_id' => $inquiry->id,
                'reason' => 'Queue dispatch connection failure.',
            ]);

            // Record failure status safely without losing the inquiry record
            try {
                $inquiry->update([
                    'notification_status' => 'failed',
                    'notification_error' => 'Failed to dispatch notification to queue.',
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $dbError) {
                Log::error('Could not record dispatch failure status to database.', [
                    'inquiry_id' => $inquiry->id,
                ]);
            }

            return false;
        }
    }
}

