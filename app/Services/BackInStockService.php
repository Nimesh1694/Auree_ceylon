<?php

namespace App\Services;

use App\Jobs\SendBackInStockAlertJob;
use App\Mail\BackInStockVerificationMail;
use App\Models\AuditLog;
use App\Models\BackInStockSubscription;
use App\Models\Product;
use App\Models\RestockEvent;
use App\Models\RestockEventClaim;
use App\Models\SubscriptionActiveLease;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BackInStockService
{
    /**
     * Detect if a stock adjustment represents a qualifying transition from unavailable to available,
     * and atomically create or update the outbox restock event.
     *
     * Invariants:
     * 1. Product must be active ($product->is_active === true).
     * 2. Stock must transition from unavailable (previous_stock <= 0) to available (new_stock > 0).
     * 3. Prevents duplicate active events: if an event is already pending_claims or processing,
     *    it does not create a new competing event.
     * 4. Must run inside the existing stock adjustment database transaction.
     */
    public function recordRestockEventIfQualifying(
        Product $product,
        int $previousStock,
        int $newStock,
        int $quantityDelta
    ): ?RestockEvent {
        // Disqualification 1: Product is inactive / draft
        if (!$product->is_active) {
            return null;
        }

        // Disqualification 2: Not a transition from unavailable (<= 0) to available (> 0)
        if ($previousStock > 0 || $newStock <= 0) {
            return null;
        }

        // Disqualification 3: Negative or zero delta cannot represent a restock
        if ($quantityDelta <= 0) {
            return null;
        }

        // Query active restock events for this product under row lock to prevent duplicate concurrent event creation
        /** @var RestockEvent|null $existingActiveEvent */
        $existingActiveEvent = RestockEvent::where('product_id', $product->id)
            ->whereIn('status', [
                RestockEvent::STATUS_PENDING_CLAIMS,
                RestockEvent::STATUS_PROCESSING,
            ])
            ->lockForUpdate()
            ->first();

        $multiplier = (float) config('back_in_stock.batch_multiplier', 2.0);
        $calculatedBatchLimit = (int) max(1, (int) round($newStock * $multiplier));
        $maxBatchLimit = config('back_in_stock.max_batch_limit');
        if ($maxBatchLimit !== null) {
            $calculatedBatchLimit = min((int) $maxBatchLimit, $calculatedBatchLimit);
        }

        if ($existingActiveEvent !== null) {
            // If already pending claims, coalesce stock numbers into existing event to avoid duplicate batches
            if ($existingActiveEvent->status === RestockEvent::STATUS_PENDING_CLAIMS) {
                $existingActiveEvent->new_stock = $newStock;
                $existingActiveEvent->restocked_quantity += $quantityDelta;
                $existingActiveEvent->batch_limit = max($existingActiveEvent->batch_limit, $calculatedBatchLimit);
                $existingActiveEvent->save();

                return $existingActiveEvent;
            }

            // If already actively processing, continuation worker handles ongoing quota; return active event
            return $existingActiveEvent;
        }

        // Create new atomic RestockEvent outbox record
        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'restocked_quantity' => $quantityDelta,
            'batch_limit' => $calculatedBatchLimit,
            'claimed_count' => 0,
            'status' => RestockEvent::STATUS_PENDING_CLAIMS,
        ]);

        AuditLog::log('restock_event_created', $product, null, [
            'restock_event_id' => $event->id,
            'product_id' => $product->id,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'restocked_quantity' => $quantityDelta,
            'batch_limit' => $calculatedBatchLimit,
            'status' => RestockEvent::STATUS_PENDING_CLAIMS,
        ]);

        return $event;
    }

    /**
     * Atomically claim the next batch of eligible subscribers for a restock event
     * using the live inventory continuation formula and strict FIFO seniority.
     */
    public function claimNextBatchForEvent(RestockEvent $event, ?string $workerId = null): int
    {
        $workerId = $workerId ?? ('worker-' . getmypid() . '-' . Str::random(6));

        $jobsToDispatch = [];
        $successfullyClaimed = 0;

        DB::transaction(function () use ($event, $workerId, &$jobsToDispatch, &$successfullyClaimed) {
            // 1. Lock event row to serialize sequence allocation
            /** @var RestockEvent|null $lockedEvent */
            $lockedEvent = RestockEvent::where('id', $event->id)->lockForUpdate()->first();
            if (!$lockedEvent || in_array($lockedEvent->status, [
                RestockEvent::STATUS_COMPLETED,
                RestockEvent::STATUS_CANCELLED,
                RestockEvent::STATUS_EXHAUSTED,
            ], true)) {
                return;
            }

            // 2. Lock product row to verify live inventory state
            /** @var Product|null $product */
            $product = Product::where('id', $lockedEvent->product_id)->lockForUpdate()->first();
            if (!$product || !$product->is_active || $product->stock_quantity <= 0) {
                // Stock depleted mid-batch or product deactivated
                $lockedEvent->status = $lockedEvent->claimed_count > 0 ? RestockEvent::STATUS_COMPLETED : RestockEvent::STATUS_EXHAUSTED;
                $lockedEvent->save();
                return;
            }

            $currentStock = (int) $product->stock_quantity;
            $multiplier = (float) config('back_in_stock.batch_multiplier', 2.0);
            $maxCronBatch = (int) config('back_in_stock.max_cron_batch', 20);

            // 3. Mandatory Live Inventory Continuation Formula:
            // quota = min(MAX_CRON_BATCH, pending_count, max(0, current_stock * multiplier - already_claimed))
            $stockAlertCeiling = (int) round($currentStock * $multiplier);

            // Active/committed business quota consumption:
            // Consumed states: claimed (in-flight), dispatched (fulfilled), failed (terminal attempt slot)
            // Non-consumed states: released, expired (returned to pending, quota restored)
            $alreadyClaimed = (int) RestockEventClaim::where('restock_event_id', $lockedEvent->id)
                ->whereIn('status', [
                    RestockEventClaim::STATUS_CLAIMED,
                    RestockEventClaim::STATUS_DISPATCHED,
                    RestockEventClaim::STATUS_FAILED,
                ])
                ->count();

            $dynamicCapacity = max(0, $stockAlertCeiling - $alreadyClaimed);

            if ($dynamicCapacity <= 0) {
                // Quota exhausted for current inventory level
                $lockedEvent->status = RestockEvent::STATUS_COMPLETED;
                $lockedEvent->save();
                return;
            }

            // Dynamically update event batch_limit if live inventory increased mid-flight
            if ($stockAlertCeiling > $lockedEvent->batch_limit) {
                $lockedEvent->batch_limit = $stockAlertCeiling;
            }

            $batchLimit = min($maxCronBatch, $dynamicCapacity);

            // 4. Query next batch of active pending subscriptions in strict FIFO seniority (id ASC)
            $candidates = BackInStockSubscription::where('product_id', $lockedEvent->product_id)
                ->where('status', BackInStockSubscription::STATUS_PENDING)
                ->where('is_active', true)
                ->orderBy('id', 'asc')
                ->limit($batchLimit)
                ->lock('for update skip locked')
                ->get();

            if ($candidates->isEmpty()) {
                // No more pending subscriptions remain
                $lockedEvent->status = RestockEvent::STATUS_COMPLETED;
                $lockedEvent->save();
                return;
            }

            $currentOrder = (int) $lockedEvent->claimed_count;
            $leaseDuration = (int) config('back_in_stock.lease_duration_seconds', 90);

            foreach ($candidates as $sub) {
                // Savepoint for atomic candidate claim + lease acquisition
                DB::beginTransaction();

                try {
                    $assignedClaimOrder = $currentOrder + 1;
                    $leaseToken = (string) Str::uuid();

                    $lastAttempt = (int) (RestockEventClaim::where('restock_event_id', $lockedEvent->id)
                        ->where('subscription_id', $sub->id)
                        ->max('attempt_number') ?? 0);

                    // Insert historical claim record
                    $claim = RestockEventClaim::create([
                        'restock_event_id' => $lockedEvent->id,
                        'subscription_id' => $sub->id,
                        'claim_order' => $assignedClaimOrder,
                        'status' => RestockEventClaim::STATUS_CLAIMED,
                        'attempt_number' => $lastAttempt + 1,
                        'lease_owner' => $workerId,
                        'lease_token' => $leaseToken,
                        'lease_acquired_at' => now(),
                        'lease_expires_at' => now()->addSeconds($leaseDuration),
                        'lease_version' => 1,
                    ]);

                    // Insert ephemeral active lease (enforces global <= 1 active lease per subscription)
                    SubscriptionActiveLease::create([
                        'subscription_id' => $sub->id,
                        'restock_event_id' => $lockedEvent->id,
                        'claim_id' => $claim->id,
                        'lease_owner' => $workerId,
                        'lease_token' => $leaseToken,
                        'lease_acquired_at' => now(),
                        'lease_expires_at' => now()->addSeconds($leaseDuration),
                        'lease_version' => 1,
                        'yield_count' => 0,
                    ]);

                    // Transition subscription to processing
                    $sub->status = BackInStockSubscription::STATUS_PROCESSING;
                    $sub->save();

                    DB::commit(); // Savepoint committed

                    $currentOrder = $assignedClaimOrder;
                    $successfullyClaimed++;

                    $jobsToDispatch[] = new SendBackInStockAlertJob(
                        $sub->id,
                        $lockedEvent->id,
                        $claim->id,
                        $leaseToken
                    );
                } catch (\Illuminate\Database\QueryException $e) {
                    DB::rollBack(); // Roll back savepoint

                    // Duplicate entry (e.g. candidate already has active lease from another event)
                    if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062) {
                        continue; // Safely skip candidate without advancing sequence number
                    }

                    throw $e;
                }
            }

            // 5. Update high-water mark and mark event processing
            $lockedEvent->claimed_count = $currentOrder;
            $lockedEvent->status = RestockEvent::STATUS_PROCESSING;
            $lockedEvent->save();
        });

        // 6. Dispatch jobs strictly AFTER transaction commits
        foreach ($jobsToDispatch as $job) {
            dispatch($job);
        }

        return $successfullyClaimed;
    }

    /**
     * Process active restock events (called by scheduled runner).
     */
    public function processActiveRestockEvents(?string $workerId = null): array
    {
        $events = RestockEvent::whereIn('status', [
            RestockEvent::STATUS_PENDING_CLAIMS,
            RestockEvent::STATUS_PROCESSING,
        ])
        ->orderBy('created_at', 'asc')
        ->limit(5)
        ->get();

        $stats = [
            'events_processed' => 0,
            'claims_allocated' => 0,
        ];

        foreach ($events as $event) {
            $claimed = $this->claimNextBatchForEvent($event, $workerId);
            $stats['events_processed']++;
            $stats['claims_allocated'] += $claimed;
        }

        return $stats;
    }

    /**
     * Recover expired/stale leases and reset orphaned processing subscriptions.
     */
    public function recoverStaleLeases(?string $workerId = null): int
    {
        $cooldown = (int) config('back_in_stock.recovery_stale_threshold_seconds', 300);
        $threshold = now()->subSeconds($cooldown);

        /** @var \Illuminate\Database\Eloquent\Collection<int, SubscriptionActiveLease> $expiredLeases */
        $expiredLeases = SubscriptionActiveLease::where('lease_expires_at', '<=', $threshold)
            ->with(['claim', 'subscription'])
            ->get();

        $recovered = 0;

        foreach ($expiredLeases as $lease) {
            DB::transaction(function () use ($lease, &$recovered) {
                // If claim was still claimed, mark as expired
                if ($lease->claim && $lease->claim->status === RestockEventClaim::STATUS_CLAIMED) {
                    $lease->claim->update([
                        'status' => RestockEventClaim::STATUS_EXPIRED,
                        'release_reason' => 'lease_expired_stale_worker',
                    ]);
                }

                // If subscription was in processing, revert back to pending for future batch
                if ($lease->subscription && $lease->subscription->status === BackInStockSubscription::STATUS_PROCESSING) {
                    $lease->subscription->update([
                        'status' => BackInStockSubscription::STATUS_PENDING,
                    ]);
                }

                $lease->delete();
                $recovered++;
            });
        }

        return $recovered;
    }

    /**
     * Normalize an email address: trimmed, lowercased, with IDN domain converted to ASCII.
     * Preserves plus-address tags.
     */
    public static function normalizeEmail(string $email): string
    {
        $email = trim($email);
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return mb_strtolower($email, 'UTF-8');
        }

        [$local, $domain] = $parts;
        if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/', $domain)) {
            $converted = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($converted !== false) {
                $domain = $converted;
            }
        }

        return mb_strtolower($local, 'UTF-8') . '@' . mb_strtolower($domain, 'UTF-8');
    }

    /**
     * Subscribe a customer or guest to back-in-stock alerts.
     */
    public function subscribe(Product $product, string $email, ?User $user = null, ?string $locale = 'en'): array
    {
        if (!$product->is_active || $product->stock_quantity > 0) {
            return [
                'success' => false,
                'status' => 'ineligible_product',
                'message' => 'Back-in-stock alerts are only available for active, out-of-stock products.',
            ];
        }

        $normalizedEmail = self::normalizeEmail($email);
        $cleanLocale = in_array($locale, ['en', 'si'], true) ? $locale : 'en';

        // Authenticated customer with verified email
        $isVerifiedCustomer = $user !== null && $user->email_verified_at !== null;

        if ($isVerifiedCustomer) {
            $emailToUse = $user->email;
            $normalizedEmail = self::normalizeEmail($emailToUse);

            return DB::transaction(function () use ($product, $user, $emailToUse, $normalizedEmail, $cleanLocale) {
                // Check if active subscription already exists
                $existingActive = BackInStockSubscription::where('product_id', $product->id)
                    ->where('normalized_email', $normalizedEmail)
                    ->where('is_active', true)
                    ->whereIn('status', [
                        BackInStockSubscription::STATUS_PENDING,
                        BackInStockSubscription::STATUS_PROCESSING,
                    ])
                    ->lockForUpdate()
                    ->first();

                if ($existingActive) {
                    return [
                        'success' => true,
                        'status' => 'already_subscribed',
                        'requires_verification' => false,
                        'message' => 'You are already subscribed to back-in-stock alerts for this product.',
                        'subscription' => $existingActive,
                    ];
                }

                $subscription = BackInStockSubscription::create([
                    'product_id' => $product->id,
                    'customer_id' => $user->id,
                    'email' => $emailToUse,
                    'normalized_email' => $normalizedEmail,
                    'locale' => $cleanLocale,
                    'status' => BackInStockSubscription::STATUS_PENDING,
                    'is_active' => true,
                    'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
                    'verified_at' => now(),
                ]);

                return [
                    'success' => true,
                    'status' => 'subscribed',
                    'requires_verification' => false,
                    'message' => 'You will receive an email alert when this item is back in stock.',
                    'subscription' => $subscription,
                ];
            });
        }

        // Guest (or unverified user requiring Double Opt-In)
        $mailToDispatch = null;

        $result = DB::transaction(function () use ($product, $user, $email, $normalizedEmail, $cleanLocale, &$mailToDispatch) {
            // Check if active subscription already exists for this email
            $existingActive = BackInStockSubscription::where('product_id', $product->id)
                ->where('normalized_email', $normalizedEmail)
                ->where('is_active', true)
                ->whereIn('status', [
                    BackInStockSubscription::STATUS_PENDING,
                    BackInStockSubscription::STATUS_PROCESSING,
                ])
                ->lockForUpdate()
                ->first();

            if ($existingActive) {
                // Generic response to avoid email address enumeration
                return [
                    'success' => true,
                    'status' => 'verification_sent',
                    'requires_verification' => true,
                    'message' => 'If eligible, a verification email has been sent to confirm your alert request.',
                ];
            }

            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = now()->addHours(24);

            $subscription = BackInStockSubscription::where('product_id', $product->id)
                ->where('normalized_email', $normalizedEmail)
                ->where('status', BackInStockSubscription::STATUS_PENDING_VERIFICATION)
                ->lockForUpdate()
                ->first();

            if ($subscription) {
                $subscription->update([
                    'email' => $email,
                    'locale' => $cleanLocale,
                    'customer_id' => $user?->id,
                    'verification_token_hash' => $tokenHash,
                    'verification_expires_at' => $expiresAt,
                ]);
            } else {
                $subscription = BackInStockSubscription::create([
                    'product_id' => $product->id,
                    'customer_id' => $user?->id,
                    'email' => $email,
                    'normalized_email' => $normalizedEmail,
                    'locale' => $cleanLocale,
                    'status' => BackInStockSubscription::STATUS_PENDING_VERIFICATION,
                    'is_active' => null,
                    'unsubscribe_token_hash' => hash('sha256', Str::random(64)),
                    'verification_token_hash' => $tokenHash,
                    'verification_expires_at' => $expiresAt,
                ]);
            }

            $mailToDispatch = new BackInStockVerificationMail($subscription, $product, $rawToken);

            return [
                'success' => true,
                'status' => 'verification_sent',
                'requires_verification' => true,
                'message' => 'Please check your email and click the confirmation link to activate your alert.',
                'subscription' => $subscription,
            ];
        });

        // Dispatch queued verification email outside transaction
        if ($mailToDispatch !== null) {
            Mail::to($email)->queue($mailToDispatch);
        }

        return $result;
    }

    /**
     * Verify a guest subscription using the raw token.
     */
    public function verifyGuestSubscription(string $rawToken): array
    {
        $tokenHash = hash('sha256', trim($rawToken));

        return DB::transaction(function () use ($tokenHash) {
            /** @var BackInStockSubscription|null $subscription */
            $subscription = BackInStockSubscription::where('verification_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$subscription) {
                return [
                    'success' => false,
                    'error' => 'invalid_token',
                    'message' => 'The verification link is invalid or has already been used.',
                ];
            }

            if ($subscription->verification_expires_at && $subscription->verification_expires_at->isPast()) {
                return [
                    'success' => false,
                    'error' => 'token_expired',
                    'message' => 'This verification link has expired. Please subscribe again.',
                ];
            }

            // Already active or verified
            if ($subscription->status === BackInStockSubscription::STATUS_PENDING && $subscription->is_active) {
                return [
                    'success' => true,
                    'already_verified' => true,
                    'message' => 'Your subscription is already confirmed and active.',
                    'subscription' => $subscription,
                ];
            }

            // Guard against duplicate active subscriptions for same product & email
            $existingActive = BackInStockSubscription::where('product_id', $subscription->product_id)
                ->where('normalized_email', $subscription->normalized_email)
                ->where('is_active', true)
                ->where('id', '!=', $subscription->id)
                ->first();

            if ($existingActive) {
                $subscription->update([
                    'status' => BackInStockSubscription::STATUS_CANCELLED,
                    'is_active' => null,
                    'verification_token_hash' => null,
                    'verification_expires_at' => null,
                ]);

                return [
                    'success' => true,
                    'already_verified' => true,
                    'message' => 'Your subscription is already confirmed and active.',
                    'subscription' => $existingActive,
                ];
            }

            // Activate subscription
            $subscription->update([
                'status' => BackInStockSubscription::STATUS_PENDING,
                'is_active' => true,
                'verified_at' => now(),
                'verification_token_hash' => null,
                'verification_expires_at' => null,
            ]);

            return [
                'success' => true,
                'already_verified' => false,
                'message' => 'Thank you! Your email alert has been confirmed.',
                'subscription' => $subscription,
            ];
        });
    }

    /**
     * Unsubscribe a customer from back-in-stock alerts.
     */
    public function unsubscribe(BackInStockSubscription $subscription): array
    {
        return DB::transaction(function () use ($subscription) {
            /** @var BackInStockSubscription|null $lockedSub */
            $lockedSub = BackInStockSubscription::where('id', $subscription->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedSub) {
                return [
                    'success' => false,
                    'message' => 'Subscription record not found.',
                ];
            }

            if ($lockedSub->status === BackInStockSubscription::STATUS_UNSUBSCRIBED) {
                return [
                    'success' => true,
                    'already_unsubscribed' => true,
                    'message' => 'You are already unsubscribed from alerts for this item.',
                    'subscription' => $lockedSub,
                ];
            }

            // If an active in-flight claim or lease exists, safely release it
            $activeClaim = RestockEventClaim::where('subscription_id', $lockedSub->id)
                ->where('status', RestockEventClaim::STATUS_CLAIMED)
                ->first();

            if ($activeClaim) {
                $activeClaim->update([
                    'status' => RestockEventClaim::STATUS_RELEASED,
                    'release_reason' => 'unsubscribed_by_customer',
                ]);
            }

            SubscriptionActiveLease::where('subscription_id', $lockedSub->id)->delete();

            $lockedSub->update([
                'status' => BackInStockSubscription::STATUS_UNSUBSCRIBED,
                'is_active' => null,
                'verification_token_hash' => null,
            ]);

            return [
                'success' => true,
                'already_unsubscribed' => false,
                'message' => 'You have successfully unsubscribed from alerts for this item.',
                'subscription' => $lockedSub,
            ];
        });
    }
}
