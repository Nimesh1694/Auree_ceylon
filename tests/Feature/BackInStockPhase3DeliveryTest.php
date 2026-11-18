<?php

namespace Tests\Feature;

use App\Jobs\SendBackInStockAlertJob;
use App\Mail\BackInStockAlertMail;
use App\Models\BackInStockDelivery;
use App\Models\BackInStockNotificationLog;
use App\Models\BackInStockSubscription;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestockEvent;
use App\Models\RestockEventClaim;
use App\Models\SubscriptionActiveLease;
use App\Models\User;
use App\Services\BackInStockService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackInStockPhase3DeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected BackInStockService $service;
    protected StockService $stockService;
    protected User $adminUser;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BackInStockService::class);
        $this->stockService = app(StockService::class);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සම රැකවරණය',
            'slug' => 'skincare',
            'is_active' => true,
        ]);
    }

    protected function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->category->id,
            'name_en' => 'Hydrating Toner',
            'name_si' => 'හයිඩ්‍රේටින් ටෝනර්',
            'slug' => 'hydrating-toner-' . Str::random(8),
            'sku' => 'TONER-' . strtoupper(Str::random(6)),
            'price' => 2800.00,
            'cost_price' => 1400.00,
            'stock_quantity' => 0,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ], $overrides));
    }

    protected function createSubscription(Product $product, array $overrides = []): BackInStockSubscription
    {
        return BackInStockSubscription::create(array_merge([
            'product_id' => $product->id,
            'email' => 'subscriber_' . Str::random(6) . '@aura.lux',
            'normalized_email' => 'subscriber_' . Str::random(6) . '@aura.lux',
            'locale' => 'en',
            'status' => BackInStockSubscription::STATUS_PENDING,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(32)),
        ], $overrides));
    }

    public function test_batch_allocation_and_strict_fifo_ordering(): void
    {
        Queue::fake();

        $product = $this->createProduct(['stock_quantity' => 0]);

        // Create 5 subscriptions in sequence
        $subs = [];
        for ($i = 1; $i <= 5; $i++) {
            $subs[] = $this->createSubscription($product, [
                'email' => "user{$i}@aura.lux",
                'normalized_email' => "user{$i}@aura.lux",
            ]);
        }

        // Restock 10 units -> creates event with batch_limit = 20
        $this->stockService->adjustStock($product, 10, InventoryMovement::TYPE_RESTOCK, 'Supplier shipment');

        $event = RestockEvent::where('product_id', $product->id)->first();
        $this->assertNotNull($event);

        // Claim batch
        $claimedCount = $this->service->claimNextBatchForEvent($event, 'worker-test-1');

        $this->assertEquals(5, $claimedCount);
        $this->assertEquals(5, $event->fresh()->claimed_count);
        $this->assertEquals(RestockEvent::STATUS_PROCESSING, $event->fresh()->status);

        // Verify claims allocated in strict FIFO order matching subscription ID
        $claims = RestockEventClaim::where('restock_event_id', $event->id)
            ->orderBy('claim_order', 'asc')
            ->get();

        $this->assertCount(5, $claims);
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals($subs[$i]->id, $claims[$i]->subscription_id);
            $this->assertEquals($i + 1, $claims[$i]->claim_order);
            $this->assertEquals(RestockEventClaim::STATUS_CLAIMED, $claims[$i]->status);
            $this->assertEquals(1, $claims[$i]->attempt_number);
        }

        // Verify all 5 received active leases
        $this->assertEquals(5, SubscriptionActiveLease::where('restock_event_id', $event->id)->count());

        // Verify jobs queued
        Queue::assertPushed(SendBackInStockAlertJob::class, 5);
    }

    public function test_live_inventory_continuation_quota(): void
    {
        Queue::fake();

        $product = $this->createProduct(['stock_quantity' => 0]);

        // Create 15 pending subscriptions
        for ($i = 1; $i <= 15; $i++) {
            $this->createSubscription($product);
        }

        // Restock 4 units -> alert ceiling = 4 * 2.0 = 8 alerts
        $this->stockService->adjustStock($product, 4, InventoryMovement::TYPE_RESTOCK, 'Small shipment');
        $event = RestockEvent::where('product_id', $product->id)->first();

        // Worker claims next batch: quota = min(20, 15, 4*2 - 0) = 8
        $claimedCount = $this->service->claimNextBatchForEvent($event, 'worker-quota');

        $this->assertEquals(8, $claimedCount);
        $this->assertEquals(8, $event->fresh()->claimed_count);

        // Second run with no new inventory: dynamic quota = min(20, 7, 8 - 8) = 0
        $secondClaimCount = $this->service->claimNextBatchForEvent($event, 'worker-quota');
        $this->assertEquals(0, $secondClaimCount);
        $this->assertEquals(RestockEvent::STATUS_COMPLETED, $event->fresh()->status);
    }

    public function test_mid_processing_stock_replenishment_expands_capacity(): void
    {
        Queue::fake();

        $product = $this->createProduct(['stock_quantity' => 0]);

        // Create 20 pending subscriptions
        for ($i = 1; $i <= 20; $i++) {
            $this->createSubscription($product);
        }

        // 1. Initial restock 5 units -> initial capacity 10
        $this->stockService->adjustStock($product, 5, InventoryMovement::TYPE_RESTOCK, 'Initial restock');
        $event = RestockEvent::where('product_id', $product->id)->first();

        $claimedBatch1 = $this->service->claimNextBatchForEvent($event, 'worker-replenish');
        $this->assertEquals(10, $claimedBatch1);
        $this->assertEquals(10, $event->fresh()->claimed_count);
        $this->assertEquals(RestockEvent::STATUS_PROCESSING, $event->fresh()->status);

        // 2. Mid-processing inventory expansion: supplier delivers 10 more units -> stock becomes 15
        $this->stockService->adjustStock($product, 10, InventoryMovement::TYPE_RESTOCK, 'Mid-flight replenishment');
        $this->assertEquals(15, $product->fresh()->stock_quantity);

        // 3. Next worker run discovers expanded capacity:
        // live stock = 15 -> alert ceiling = 30 -> remaining capacity = 30 - 10 = 20
        // Remaining pending = 10 -> claims remaining 10!
        $claimedBatch2 = $this->service->claimNextBatchForEvent($event, 'worker-replenish');
        $this->assertEquals(10, $claimedBatch2);
        $this->assertEquals(20, $event->fresh()->claimed_count);
        $this->assertEquals(30, $event->fresh()->batch_limit); // Batch limit expanded to 30
    }

    public function test_jit_stock_depletion_releases_claim_and_reverts_subscription(): void
    {
        Queue::fake();

        $product = $this->createProduct(['stock_quantity' => 0]);
        $sub = $this->createSubscription($product);

        $this->stockService->adjustStock($product, 5, InventoryMovement::TYPE_RESTOCK, 'Restock');
        $event = RestockEvent::where('product_id', $product->id)->first();

        // Claim subscriber
        $this->service->claimNextBatchForEvent($event, 'worker-jit');
        $claim = RestockEventClaim::where('subscription_id', $sub->id)->first();
        $lease = SubscriptionActiveLease::where('subscription_id', $sub->id)->first();

        $this->assertEquals(BackInStockSubscription::STATUS_PROCESSING, $sub->fresh()->status);

        // Simulate stock depleted to 0 by other orders before job executes
        DB::table('products')->where('id', $product->id)->update(['stock_quantity' => 0]);

        // Execute job
        $job = new SendBackInStockAlertJob($sub->id, $event->id, $claim->id, $lease->lease_token);
        $job->handle();

        // Assert JIT check released claim and subscription returned to pending
        $this->assertEquals(RestockEventClaim::STATUS_RELEASED, $claim->fresh()->status);
        $this->assertEquals('stock_depleted_before_dispatch', $claim->fresh()->release_reason);
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING, $sub->fresh()->status);
        $this->assertNull(SubscriptionActiveLease::where('subscription_id', $sub->id)->first());
    }

    public function test_pre_send_fencing_rejects_stale_worker_token(): void
    {
        Mail::fake();

        $product = $this->createProduct(['stock_quantity' => 10]);
        $sub = $this->createSubscription($product);

        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        $claim = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 1,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 1,
        ]);

        SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event->id,
            'claim_id' => $claim->id,
            'lease_owner' => 'worker-new',
            'lease_token' => 'VALID-TOKEN-NEW',
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 2,
            'yield_count' => 0,
        ]);

        // Stale job with old token
        $staleJob = new SendBackInStockAlertJob($sub->id, $event->id, $claim->id, 'STALE-TOKEN-OLD');
        $staleJob->handle();

        // Stale worker must not dispatch mail!
        Mail::assertNothingSent();

        // Database records untouched
        $this->assertEquals(RestockEventClaim::STATUS_CLAIMED, $claim->fresh()->status);
        $this->assertEquals('VALID-TOKEN-NEW', SubscriptionActiveLease::where('subscription_id', $sub->id)->value('lease_token'));
    }

    public function test_successful_smtp_delivery_completes_pipeline(): void
    {
        Mail::fake();

        $product = $this->createProduct(['stock_quantity' => 10]);
        $sub = $this->createSubscription($product, ['email' => 'successful@aura.lux', 'locale' => 'en']);

        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        $claim = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 1,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 1,
        ]);

        $token = (string) Str::uuid();
        SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event->id,
            'claim_id' => $claim->id,
            'lease_owner' => 'worker-1',
            'lease_token' => $token,
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
        ]);

        $job = new SendBackInStockAlertJob($sub->id, $event->id, $claim->id, $token);
        $job->handle();

        // Mail dispatched
        Mail::assertSent(BackInStockAlertMail::class, function ($mail) use ($sub) {
            return $mail->hasTo('successful@aura.lux');
        });

        // Delivery marked accepted
        $delivery = BackInStockDelivery::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals(BackInStockDelivery::STATUS_PROVIDER_ACCEPTED, $delivery->status);

        // Notification log recorded
        $log = BackInStockNotificationLog::where('delivery_id', $delivery->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(BackInStockNotificationLog::STATUS_PROVIDER_ACCEPTED, $log->status);
        $this->assertEquals(1, $log->attempt_number);

        // Subscription transitioned to notified and deactivated
        $sub->refresh();
        $this->assertEquals(BackInStockSubscription::STATUS_NOTIFIED, $sub->status);
        $this->assertNotNull($sub->notified_at);
        $this->assertNull($sub->is_active);

        // Claim marked dispatched
        $this->assertEquals(RestockEventClaim::STATUS_DISPATCHED, $claim->fresh()->status);

        // Active lease deleted
        $this->assertNull(SubscriptionActiveLease::where('subscription_id', $sub->id)->first());
    }

    public function test_smtp_transport_failure_increments_yield_and_releases_with_backoff(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $sub = $this->createSubscription($product);

        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        $claim = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 1,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 1,
        ]);

        $token = (string) Str::uuid();
        $lease = SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event->id,
            'claim_id' => $claim->id,
            'lease_owner' => 'worker-1',
            'lease_token' => $token,
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
        ]);

        // Mock Mail facade to throw a transport exception
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Connection timed out'));

        $job = new SendBackInStockAlertJob($sub->id, $event->id, $claim->id, $token);
        
        try {
            $job->handle();
        } catch (\Throwable $e) {
            // In unit tests without queue worker, release() throws or sets released state
        }

        // Notification log marked failed
        $delivery = BackInStockDelivery::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals(BackInStockDelivery::STATUS_INITIATED, $delivery->status); // Still retryable

        $log = BackInStockNotificationLog::where('delivery_id', $delivery->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(BackInStockNotificationLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('Connection timed out', $log->error_message);

        // Durable yield counter incremented
        $this->assertEquals(1, $lease->fresh()->yield_count);
    }

    public function test_exhaustion_of_business_attempts_routes_to_manual_review(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $sub = $this->createSubscription($product);

        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        $claim = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 1,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 1,
        ]);

        $token = (string) Str::uuid();
        SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event->id,
            'claim_id' => $claim->id,
            'lease_owner' => 'worker-1',
            'lease_token' => $token,
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 2,
        ]);

        // Create delivery with 3 previous failed attempts
        $delivery = BackInStockDelivery::create([
            'subscription_id' => $sub->id,
            'product_id' => $product->id,
            'restock_event_id' => $event->id,
            'logical_delivery_key' => hash('sha256', "sub:{$sub->id}:event:{$event->id}"),
            'status' => BackInStockDelivery::STATUS_INITIATED,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            BackInStockNotificationLog::create([
                'delivery_id' => $delivery->id,
                'attempt_number' => $i,
                'idempotency_key' => hash('sha256', "del:{$delivery->id}:attempt:{$i}"),
                'status' => BackInStockNotificationLog::STATUS_FAILED,
            ]);
        }

        // 4th attempt exceeds max business attempts (3)
        $job = new SendBackInStockAlertJob($sub->id, $event->id, $claim->id, $token);
        $job->handle();

        $this->assertEquals(BackInStockDelivery::STATUS_MANUAL_REVIEW, $delivery->fresh()->status);
        $this->assertEquals(BackInStockSubscription::STATUS_MANUAL_REVIEW, $sub->fresh()->status);
        $this->assertEquals(RestockEventClaim::STATUS_FAILED, $claim->fresh()->status);
        $this->assertNull(SubscriptionActiveLease::where('subscription_id', $sub->id)->first());
    }

    public function test_stale_lease_recovery_command(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $sub = $this->createSubscription($product, ['status' => BackInStockSubscription::STATUS_PROCESSING]);

        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        $claim = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 1,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 1,
        ]);

        // Expired lease from 10 minutes ago
        SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event->id,
            'claim_id' => $claim->id,
            'lease_owner' => 'crashed-worker',
            'lease_token' => 'CRASHED-TOKEN',
            'lease_acquired_at' => now()->subMinutes(15),
            'lease_expires_at' => now()->subMinutes(10), // expired!
            'lease_version' => 1,
            'yield_count' => 0,
        ]);

        // Run recovery command
        $this->artisan('back-in-stock:recover-stale-leases')
            ->expectsOutput('Recovered 1 expired lease(s).')
            ->assertSuccessful();

        // Assert lease deleted, claim marked expired, subscription reverted to pending
        $this->assertNull(SubscriptionActiveLease::where('subscription_id', $sub->id)->first());
        $this->assertEquals(RestockEventClaim::STATUS_EXPIRED, $claim->fresh()->status);
        $this->assertEquals('lease_expired_stale_worker', $claim->fresh()->release_reason);
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING, $sub->fresh()->status);
    }

    public function test_expired_claim_is_reclaimed_in_subsequent_batch_with_incremented_attempt_and_order(): void
    {
        Queue::fake();

        $product = $this->createProduct(['stock_quantity' => 0]);
        $sub1 = $this->createSubscription($product, ['email' => 'sub1@aura.lux']);
        $sub2 = $this->createSubscription($product, ['email' => 'sub2@aura.lux']);

        // Stock 1, multiplier 2 -> Alert ceiling = 2
        $this->stockService->adjustStock($product, 1, InventoryMovement::TYPE_RESTOCK, 'Restock 1 unit');
        $event = RestockEvent::where('product_id', $product->id)->first();

        // 1. Batch 1 claims both subscribers
        $claimed1 = $this->service->claimNextBatchForEvent($event, 'worker-1');
        $this->assertEquals(2, $claimed1);
        $this->assertEquals(2, $event->fresh()->claimed_count);

        $claim1 = RestockEventClaim::where('subscription_id', $sub1->id)->first();
        $this->assertEquals(1, $claim1->claim_order);
        $this->assertEquals(1, $claim1->attempt_number);
        $this->assertEquals(RestockEventClaim::STATUS_CLAIMED, $claim1->status);

        // 2. Simulate worker crash on Sub 1 -> lease expires
        SubscriptionActiveLease::where('subscription_id', $sub1->id)->update([
            'lease_expires_at' => now()->subMinutes(10),
        ]);

        // 3. Stale lease recovery runs
        $this->artisan('back-in-stock:recover-stale-leases')->assertSuccessful();

        $this->assertEquals(RestockEventClaim::STATUS_EXPIRED, $claim1->fresh()->status);
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING, $sub1->fresh()->status);
        $this->assertNull(SubscriptionActiveLease::where('subscription_id', $sub1->id)->first());

        // 4. Continuation worker runs:
        // Quota: ceiling = 2. active/fulfilled: Sub 2 is 'claimed' (1). Expired Sub 1 = 0.
        // Dynamic capacity = 2 - 1 = 1! Sub 1 must be reclaimed!
        $claimed2 = $this->service->claimNextBatchForEvent($event, 'worker-2');
        $this->assertEquals(1, $claimed2);

        // High water mark advanced monotonically (2 -> 3), never decremented!
        $this->assertEquals(3, $event->fresh()->claimed_count);
        $this->assertEquals(BackInStockSubscription::STATUS_PROCESSING, $sub1->fresh()->status);

        // Sub 1 now has two historical claim records
        $sub1Claims = RestockEventClaim::where('subscription_id', $sub1->id)->orderBy('attempt_number', 'asc')->get();
        $this->assertCount(2, $sub1Claims);

        // Attempt 1: expired
        $this->assertEquals(1, $sub1Claims[0]->claim_order);
        $this->assertEquals(1, $sub1Claims[0]->attempt_number);
        $this->assertEquals(RestockEventClaim::STATUS_EXPIRED, $sub1Claims[0]->status);

        // Attempt 2: claimed with new order and fresh lease
        $this->assertEquals(3, $sub1Claims[1]->claim_order);
        $this->assertEquals(2, $sub1Claims[1]->attempt_number);
        $this->assertEquals(RestockEventClaim::STATUS_CLAIMED, $sub1Claims[1]->status);

        $newLease = SubscriptionActiveLease::where('subscription_id', $sub1->id)->first();
        $this->assertNotNull($newLease);
        $this->assertEquals($sub1Claims[1]->id, $newLease->claim_id);
    }

    public function test_released_claim_due_to_jit_depletion_is_reclaimed_when_stock_restored(): void
    {
        Queue::fake();

        $product = $this->createProduct(['stock_quantity' => 0]);
        $sub1 = $this->createSubscription($product, ['email' => 'sub1@aura.lux']);
        $sub2 = $this->createSubscription($product, ['email' => 'sub2@aura.lux']);

        // Stock 1, multiplier 2 -> Alert ceiling = 2
        $this->stockService->adjustStock($product, 1, InventoryMovement::TYPE_RESTOCK, 'Restock 1 unit');
        $event = RestockEvent::where('product_id', $product->id)->first();

        // 1. Initial claim batch allocates both subscriptions
        $claimed1 = $this->service->claimNextBatchForEvent($event, 'worker-1');
        $this->assertEquals(2, $claimed1);
        $this->assertEquals(2, $event->fresh()->claimed_count);

        // 2. Stock drops to 0 before jobs dispatch
        DB::table('products')->where('id', $product->id)->update(['stock_quantity' => 0]);

        // Execute JIT release for both subscriptions
        foreach ([$sub1, $sub2] as $sub) {
            $claim = RestockEventClaim::where('subscription_id', $sub->id)->latest('id')->first();
            $lease = SubscriptionActiveLease::where('subscription_id', $sub->id)->first();
            $job = new SendBackInStockAlertJob($sub->id, $event->id, $claim->id, $lease->lease_token);
            $job->handle();

            $this->assertEquals(RestockEventClaim::STATUS_RELEASED, $claim->fresh()->status);
            $this->assertEquals(BackInStockSubscription::STATUS_PENDING, $sub->fresh()->status);
            $this->assertNull(SubscriptionActiveLease::where('subscription_id', $sub->id)->first());
        }

        // 3. Physical stock is replenished to 1
        $this->stockService->adjustStock($product, 1, InventoryMovement::TYPE_RESTOCK, 'Replacement shipment');
        $this->assertEquals(RestockEvent::STATUS_PROCESSING, $event->fresh()->status);

        // 4. Continuation worker runs:
        // Quota: stock = 1, ceiling = 2.
        // Active/fulfilled: 0 (both existing claims are released).
        // Dynamic capacity = 2 - 0 = 2!
        $claimed2 = $this->service->claimNextBatchForEvent($event, 'worker-2');
        $this->assertEquals(2, $claimed2);

        // High water mark advanced monotonically from 2 to 4
        $this->assertEquals(4, $event->fresh()->claimed_count);
        $this->assertEquals(BackInStockSubscription::STATUS_PROCESSING, $sub1->fresh()->status);
        $this->assertEquals(BackInStockSubscription::STATUS_PROCESSING, $sub2->fresh()->status);

        // Both subscriptions have Attempt 1 (released) and Attempt 2 (claimed)
        $sub1Claims = RestockEventClaim::where('subscription_id', $sub1->id)->orderBy('attempt_number', 'asc')->get();
        $this->assertCount(2, $sub1Claims);
        $this->assertEquals(1, $sub1Claims[0]->attempt_number);
        $this->assertEquals(RestockEventClaim::STATUS_RELEASED, $sub1Claims[0]->status);
        $this->assertEquals(2, $sub1Claims[1]->attempt_number);
        $this->assertEquals(3, $sub1Claims[1]->claim_order);
        $this->assertEquals(RestockEventClaim::STATUS_CLAIMED, $sub1Claims[1]->status);

        $sub2Claims = RestockEventClaim::where('subscription_id', $sub2->id)->orderBy('attempt_number', 'asc')->get();
        $this->assertCount(2, $sub2Claims);
        $this->assertEquals(1, $sub2Claims[0]->attempt_number);
        $this->assertEquals(RestockEventClaim::STATUS_RELEASED, $sub2Claims[0]->status);
        $this->assertEquals(2, $sub2Claims[1]->attempt_number);
        $this->assertEquals(4, $sub2Claims[1]->claim_order);
        $this->assertEquals(RestockEventClaim::STATUS_CLAIMED, $sub2Claims[1]->status);
    }

    public function test_quota_capacity_counts_active_and_dispatched_excluding_released_and_expired(): void
    {
        Queue::fake();

        $product = $this->createProduct(['stock_quantity' => 5]); // stock = 5, multiplier = 2 -> ceiling = 10

        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => 0,
            'new_stock' => 5,
            'restocked_quantity' => 5,
            'batch_limit' => 10,
            'claimed_count' => 10,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        // Create 10 existing claim records simulating mixed outcomes:
        // - 4 dispatched
        // - 2 claimed (active in-flight)
        // - 1 failed (terminal manual review)
        // - 2 released
        // - 1 expired
        // Total consuming quota = 4 + 2 + 1 = 7. Non-consuming = 2 + 1 = 3.
        $statuses = [
            RestockEventClaim::STATUS_DISPATCHED,
            RestockEventClaim::STATUS_DISPATCHED,
            RestockEventClaim::STATUS_DISPATCHED,
            RestockEventClaim::STATUS_DISPATCHED,
            RestockEventClaim::STATUS_CLAIMED,
            RestockEventClaim::STATUS_CLAIMED,
            RestockEventClaim::STATUS_FAILED,
            RestockEventClaim::STATUS_RELEASED,
            RestockEventClaim::STATUS_RELEASED,
            RestockEventClaim::STATUS_EXPIRED,
        ];

        for ($i = 0; $i < 10; $i++) {
            $dummySub = $this->createSubscription($product, ['status' => BackInStockSubscription::STATUS_NOTIFIED]);
            RestockEventClaim::create([
                'restock_event_id' => $event->id,
                'subscription_id' => $dummySub->id,
                'claim_order' => $i + 1,
                'status' => $statuses[$i],
                'attempt_number' => 1,
            ]);
        }

        // Create 3 active pending subscriptions
        $newSub1 = $this->createSubscription($product);
        $newSub2 = $this->createSubscription($product);
        $newSub3 = $this->createSubscription($product);

        // Continuation worker runs:
        // Available capacity: ceiling(10) - consumed(7) = 3!
        // Should claim exactly the 3 pending subscribers!
        $claimed = $this->service->claimNextBatchForEvent($event, 'worker-quota-check');
        $this->assertEquals(3, $claimed);

        // Sequence counter incremented from 10 to 13
        $this->assertEquals(13, $event->fresh()->claimed_count);
        $this->assertEquals(BackInStockSubscription::STATUS_PROCESSING, $newSub1->fresh()->status);
        $this->assertEquals(BackInStockSubscription::STATUS_PROCESSING, $newSub2->fresh()->status);
        $this->assertEquals(BackInStockSubscription::STATUS_PROCESSING, $newSub3->fresh()->status);

        // If another pending subscriber exists, next worker run sees quota exhausted (10/10) and completes event
        $extraSub = $this->createSubscription($product);
        $claimedExtra = $this->service->claimNextBatchForEvent($event, 'worker-full');
        $this->assertEquals(0, $claimedExtra);
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING, $extraSub->fresh()->status);
        $this->assertEquals(RestockEvent::STATUS_COMPLETED, $event->fresh()->status);
    }

    public function test_smtp_acceptance_preserves_concurrent_unsubscribed_state(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);

        // Target subscriber currently in processing
        $sub = $this->createSubscription($product, [
            'email' => 'racing.unsub@aura.lux',
            'status' => BackInStockSubscription::STATUS_PROCESSING,
            'is_active' => true,
        ]);

        // Unrelated subscription to verify no collateral state corruption
        $unrelatedSub = $this->createSubscription($product, [
            'email' => 'unrelated@aura.lux',
            'status' => BackInStockSubscription::STATUS_PROCESSING,
            'is_active' => true,
        ]);

        $event = RestockEvent::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 2,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        $claim = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 1,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 1,
        ]);

        $unrelatedClaim = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $unrelatedSub->id,
            'claim_order' => 2,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 1,
        ]);

        $token = (string) Str::uuid();
        SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event->id,
            'claim_id' => $claim->id,
            'lease_owner' => 'worker-racing',
            'lease_token' => $token,
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
        ]);

        // Mock SMTP delivery: simulate customer clicking unsubscribe while worker is in flight with SMTP
        Mail::shouldReceive('to->send')
            ->once()
            ->andReturnUsing(function ($mailable) use ($sub) {
                // Simulate customer unsubscribe committed in database during active SMTP call
                $unsubResult = app(BackInStockService::class)->unsubscribe($sub);
                $this->assertTrue($unsubResult['success']);
                $this->assertEquals(BackInStockSubscription::STATUS_UNSUBSCRIBED, $sub->fresh()->status);

                return null; // Remote SMTP responds with 250 OK
            });

        $job = new SendBackInStockAlertJob($sub->id, $event->id, $claim->id, $token);
        $job->handle();

        // 1. Subscription status remains unsubscribed (NOT overwritten with notified)
        $this->assertEquals(BackInStockSubscription::STATUS_UNSUBSCRIBED, $sub->fresh()->status);

        // 2. Subscription is_active remains NULL
        $this->assertNull($sub->fresh()->is_active);

        // 3. Delivery status remains provider_accepted (SMTP fact is recorded)
        $delivery = BackInStockDelivery::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals(BackInStockDelivery::STATUS_PROVIDER_ACCEPTED, $delivery->status);

        // 4. Accepted delivery audit record is preserved
        $log = BackInStockNotificationLog::where('delivery_id', $delivery->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(BackInStockNotificationLog::STATUS_PROVIDER_ACCEPTED, $log->status);
        $this->assertNotNull($log->dispatched_at);

        // 5. No active lease remains for the unsubscribed subscription
        $this->assertNull(SubscriptionActiveLease::where('subscription_id', $sub->id)->first());

        // 6. No future batch can claim the unsubscribed subscription
        $claimedCount = $this->service->claimNextBatchForEvent($event, 'worker-next-check');
        $this->assertEquals(0, $claimedCount); // No active pending subscribers available
        $this->assertEquals(0, RestockEventClaim::where('subscription_id', $sub->id)->where('status', RestockEventClaim::STATUS_CLAIMED)->count());
        $this->assertEquals(BackInStockSubscription::STATUS_UNSUBSCRIBED, $sub->fresh()->status);
        $this->assertNull($sub->fresh()->is_active);

        // 7. No unrelated subscription or claim state was modified
        $this->assertEquals(BackInStockSubscription::STATUS_PROCESSING, $unrelatedSub->fresh()->status);
        $this->assertEquals(RestockEventClaim::STATUS_CLAIMED, $unrelatedClaim->fresh()->status);
        $this->assertEquals(1, $unrelatedClaim->fresh()->attempt_number);
    }
}
