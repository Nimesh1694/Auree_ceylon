<?php

namespace App\Jobs;

use App\Mail\BackInStockAlertMail;
use App\Models\BackInStockDelivery;
use App\Models\BackInStockNotificationLog;
use App\Models\BackInStockSubscription;
use App\Models\Product;
use App\Models\RestockEvent;
use App\Models\RestockEventClaim;
use App\Models\SubscriptionActiveLease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class SendBackInStockAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Absolute 15-minute queue deadline timestamp.
     */
    public int $deadlineAt;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $restockEventId,
        public readonly int $claimId,
        public readonly string $leaseToken,
        ?int $deadlineAt = null
    ) {
        $this->deadlineAt = $deadlineAt ?? (now()->addSeconds(config('back_in_stock.job_deadline_seconds', 900))->timestamp);
        $this->onQueue(config('back_in_stock.queue', 'default'));
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return \Illuminate\Support\Carbon::createFromTimestamp($this->deadlineAt);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Absolute Deadline Enforcement
        if (now()->timestamp > $this->deadlineAt) {
            Log::warning("BackInStock job deadline expired for subscription {$this->subscriptionId}. Releasing.");
            $this->handleDeadlineExhaustion();
            return;
        }

        // 2. Pre-Send Fencing Check (Fencing Token Guard)
        /** @var SubscriptionActiveLease|null $lease */
        $lease = SubscriptionActiveLease::where('subscription_id', $this->subscriptionId)->first();

        if ($lease === null || $lease->lease_token !== $this->leaseToken) {
            Log::info("Fencing check rejected stale worker: lease missing or token mismatch for subscription {$this->subscriptionId}.");
            return; // Dropped without side effects; lease owned by another worker or completed
        }

        if ($lease->lease_expires_at <= now()) {
            Log::warning("Fencing check: lease expired for subscription {$this->subscriptionId}.");
            return; // Stale worker with expired lease must not deliver
        }

        // 3. Load Models
        $subscription = BackInStockSubscription::find($this->subscriptionId);
        $claim = RestockEventClaim::find($this->claimId);
        $product = Product::find($subscription?->product_id);

        if (!$subscription || !$claim || !$product) {
            $lease->delete();
            return;
        }

        // Check if subscriber unsubscribed or cancelled while queued
        if (!$subscription->is_active || in_array($subscription->status, [
            BackInStockSubscription::STATUS_CANCELLED,
            BackInStockSubscription::STATUS_UNSUBSCRIBED,
        ], true)) {
            $claim->status = RestockEventClaim::STATUS_RELEASED;
            $claim->release_reason = 'subscriber_unsubscribed_while_queued';
            $claim->save();
            $lease->delete();
            return;
        }

        // 4. JIT Product Availability Re-Validation (Stock Depletion Guard)
        if (!$product->is_active || $product->stock_quantity <= 0) {
            Log::info("JIT check failed: product {$product->id} depleted or inactive before dispatch. Releasing claim.");
            
            DB::transaction(function () use ($subscription, $claim, $lease) {
                $claim->status = RestockEventClaim::STATUS_RELEASED;
                $claim->release_reason = 'stock_depleted_before_dispatch';
                $claim->save();

                $subscription->status = BackInStockSubscription::STATUS_PENDING;
                $subscription->save();

                $lease->delete();
            });

            return;
        }

        // 5. Logical Delivery Record (Idempotent per subscription per event)
        $logicalKey = hash('sha256', "sub:{$this->subscriptionId}:event:{$this->restockEventId}");
        
        /** @var BackInStockDelivery $delivery */
        $delivery = BackInStockDelivery::firstOrCreate(
            ['logical_delivery_key' => $logicalKey],
            [
                'subscription_id' => $this->subscriptionId,
                'product_id' => $product->id,
                'restock_event_id' => $this->restockEventId,
                'status' => BackInStockDelivery::STATUS_INITIATED,
            ]
        );

        // Never downgrade an already accepted provider delivery!
        if ($delivery->status === BackInStockDelivery::STATUS_PROVIDER_ACCEPTED) {
            $lease->delete();
            return;
        }

        // 6. Business Delivery Attempt Counter
        $priorAttempts = BackInStockNotificationLog::where('delivery_id', $delivery->id)->count();
        $attemptNumber = $priorAttempts + 1;
        $maxAttempts = (int) config('back_in_stock.max_business_attempts', 3);

        if ($attemptNumber > $maxAttempts) {
            Log::warning("Max business delivery attempts ({$maxAttempts}) exhausted for delivery {$delivery->id}.");
            $this->handleMaxBusinessAttemptsExhausted($delivery, $subscription, $claim, $lease);
            return;
        }

        // 7. Notification Attempt Log (Initiated)
        $idempotencyKey = hash('sha256', "del:{$delivery->id}:attempt:{$attemptNumber}");
        
        $log = BackInStockNotificationLog::create([
            'delivery_id' => $delivery->id,
            'attempt_number' => $attemptNumber,
            'idempotency_key' => $idempotencyKey,
            'status' => BackInStockNotificationLog::STATUS_INITIATED,
        ]);

        // 8. Lease Heartbeat Extension Before Network Transport
        $leaseDuration = (int) config('back_in_stock.lease_duration_seconds', 90);
        $lease->lease_expires_at = now()->addSeconds($leaseDuration);
        $lease->heartbeat_at = now();
        $lease->save();

        // 9. Physical SMTP Delivery (NO DATABASE LOCKS HELD DURING NETWORK TRANSPORT)
        try {
            Mail::to($subscription->email)->send(new BackInStockAlertMail($subscription, $product));

            // Remote SMTP succeeded!
            $log->update([
                'status' => BackInStockNotificationLog::STATUS_PROVIDER_ACCEPTED,
                'dispatched_at' => now(),
            ]);

            $delivery->update([
                'status' => BackInStockDelivery::STATUS_PROVIDER_ACCEPTED,
            ]);

            DB::transaction(function () {
                // Subscription transitioned to notified only if not unsubscribed concurrently
                /** @var BackInStockSubscription|null $latestSub */
                $latestSub = BackInStockSubscription::where('id', $this->subscriptionId)
                    ->lockForUpdate()
                    ->first();

                if ($latestSub) {
                    if ($latestSub->status !== BackInStockSubscription::STATUS_UNSUBSCRIBED) {
                        $latestSub->update([
                            'status' => BackInStockSubscription::STATUS_NOTIFIED,
                            'notified_at' => now(),
                            'is_active' => null,
                        ]);
                    } else {
                        // Customer unsubscribed concurrently during SMTP send; preserve unsubscribed status and nullify active flag
                        if ($latestSub->is_active !== null) {
                            $latestSub->update(['is_active' => null]);
                        }
                    }
                }

                // Claim transitioned to dispatched only if still in claimed status
                /** @var RestockEventClaim|null $latestClaim */
                $latestClaim = RestockEventClaim::where('id', $this->claimId)
                    ->lockForUpdate()
                    ->first();

                if ($latestClaim && $latestClaim->status === RestockEventClaim::STATUS_CLAIMED) {
                    $latestClaim->update([
                        'status' => RestockEventClaim::STATUS_DISPATCHED,
                    ]);
                }

                // Ephemeral lock cleanly cleared for this worker's token
                SubscriptionActiveLease::where('subscription_id', $this->subscriptionId)
                    ->where('lease_token', $this->leaseToken)
                    ->delete();
            });

        } catch (Throwable $e) {
            $this->handleTransportException($e, $delivery, $log, $subscription, $claim, $lease, $attemptNumber, $maxAttempts);
        }
    }

    /**
     * Handle SMTP transport failure with bounded retries and exponential backoff.
     */
    protected function handleTransportException(
        Throwable $e,
        BackInStockDelivery $delivery,
        BackInStockNotificationLog $log,
        BackInStockSubscription $subscription,
        RestockEventClaim $claim,
        SubscriptionActiveLease $lease,
        int $attemptNumber,
        int $maxAttempts
    ): void {
        $errorMessage = substr($e->getMessage(), 0, 500);

        // Permanent rejection (e.g. 5xx syntax error, invalid mailbox)
        $isPermanent = $e->getCode() >= 500 && $e->getCode() < 600;

        if ($isPermanent) {
            $log->update([
                'status' => BackInStockNotificationLog::STATUS_PROVIDER_REJECTED,
                'error_message' => $errorMessage,
            ]);

            $delivery->update([
                'status' => BackInStockDelivery::STATUS_PROVIDER_REJECTED,
            ]);

            DB::transaction(function () {
                $latestSub = BackInStockSubscription::where('id', $this->subscriptionId)
                    ->lockForUpdate()
                    ->first();

                if ($latestSub && $latestSub->status !== BackInStockSubscription::STATUS_UNSUBSCRIBED) {
                    $latestSub->update([
                        'status' => BackInStockSubscription::STATUS_FAILED,
                        'is_active' => null,
                    ]);
                }

                $latestClaim = RestockEventClaim::where('id', $this->claimId)
                    ->lockForUpdate()
                    ->first();

                if ($latestClaim && $latestClaim->status === RestockEventClaim::STATUS_CLAIMED) {
                    $latestClaim->update([
                        'status' => RestockEventClaim::STATUS_FAILED,
                        'release_reason' => 'provider_rejected',
                    ]);
                }

                SubscriptionActiveLease::where('subscription_id', $this->subscriptionId)
                    ->where('lease_token', $this->leaseToken)
                    ->delete();
            });

            return;
        }

        // Temporary failure (4xx, connection timeout, socket drop)
        $log->update([
            'status' => BackInStockNotificationLog::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);

        if ($attemptNumber < $maxAttempts) {
            // Calculate backoff: attempt 1 -> 60s, attempt 2 -> 300s
            $backoffSeconds = $attemptNumber === 1 ? 60 : 300;

            // Atomically update durable yield counter
            DB::table('subscription_active_leases')
                ->where('subscription_id', $this->subscriptionId)
                ->update([
                    'yield_count' => DB::raw('yield_count + 1'),
                    'lease_expires_at' => now()->addSeconds($backoffSeconds + 90),
                    'updated_at' => now(),
                ]);

            $delivery->update([
                'status' => BackInStockDelivery::STATUS_INITIATED, // Still retryable
            ]);

            // Release back to queue with delay
            $this->release($backoffSeconds);
            return;
        }

        // Exhausted business delivery attempts -> transition to manual review or failed
        $this->handleMaxBusinessAttemptsExhausted($delivery, $subscription, $claim, $lease, $errorMessage);
    }

    /**
     * Handle exhaustion of business delivery attempts.
     */
    protected function handleMaxBusinessAttemptsExhausted(
        BackInStockDelivery $delivery,
        BackInStockSubscription $subscription,
        RestockEventClaim $claim,
        SubscriptionActiveLease $lease,
        ?string $errorMessage = null
    ): void {
        // If delivery outcome is ambiguous after multiple transport failures, route to manual review
        $status = BackInStockDelivery::STATUS_MANUAL_REVIEW;

        $delivery->update([
            'status' => $status,
        ]);

        DB::transaction(function () use ($errorMessage) {
            $latestSub = BackInStockSubscription::where('id', $this->subscriptionId)
                ->lockForUpdate()
                ->first();

            if ($latestSub && $latestSub->status !== BackInStockSubscription::STATUS_UNSUBSCRIBED) {
                $latestSub->update([
                    'status' => BackInStockSubscription::STATUS_MANUAL_REVIEW,
                ]);
            }

            $latestClaim = RestockEventClaim::where('id', $this->claimId)
                ->lockForUpdate()
                ->first();

            if ($latestClaim && $latestClaim->status === RestockEventClaim::STATUS_CLAIMED) {
                $latestClaim->update([
                    'status' => RestockEventClaim::STATUS_FAILED,
                    'release_reason' => $errorMessage ?? 'max_business_attempts_exhausted',
                ]);
            }

            SubscriptionActiveLease::where('subscription_id', $this->subscriptionId)
                ->where('lease_token', $this->leaseToken)
                ->delete();
        });
    }

    /**
     * Handle absolute 15-minute queue deadline exhaustion.
     */
    protected function handleDeadlineExhaustion(): void
    {
        $lease = SubscriptionActiveLease::where('subscription_id', $this->subscriptionId)->first();
        if ($lease && $lease->lease_token === $this->leaseToken) {
            $lease->delete();
        }

        $claim = RestockEventClaim::find($this->claimId);
        if ($claim && $claim->status === RestockEventClaim::STATUS_CLAIMED) {
            $claim->update([
                'status' => RestockEventClaim::STATUS_EXPIRED,
                'release_reason' => 'queue_deadline_exhausted',
            ]);
        }
    }

    /**
     * Handle job failure called by Laravel worker when tries exhausted or unhandled exception.
     */
    public function failed(?Throwable $exception): void
    {
        // Safe failed handler: never downgrade provider_accepted
        $delivery = BackInStockDelivery::where('logical_delivery_key', hash('sha256', "sub:{$this->subscriptionId}:event:{$this->restockEventId}"))->first();
        
        if ($delivery && $delivery->status !== BackInStockDelivery::STATUS_PROVIDER_ACCEPTED) {
            $delivery->update(['status' => BackInStockDelivery::STATUS_MANUAL_REVIEW]);
        }

        $lease = SubscriptionActiveLease::where('subscription_id', $this->subscriptionId)->first();
        if ($lease && $lease->lease_token === $this->leaseToken) {
            $lease->delete();
        }
    }
}
