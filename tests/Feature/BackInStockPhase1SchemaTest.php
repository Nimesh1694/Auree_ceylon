<?php

namespace Tests\Feature;

use App\Models\BackInStockDelivery;
use App\Models\BackInStockNotificationLog;
use App\Models\BackInStockSubscription;
use App\Models\Product;
use App\Models\RestockEvent;
use App\Models\RestockEventClaim;
use App\Models\SubscriptionActiveLease;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackInStockPhase1SchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function createTestUser(): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'BackInStock Tester',
            'email' => 'bis-tester-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createTestProduct(): int
    {
        return DB::table('products')->insertGetId([
            'name_en' => 'Phase 1 Test Serum',
            'name_si' => 'ටෙස්ට් සීරම්',
            'slug' => 'test-serum-' . Str::random(8),
            'sku' => 'SKU-' . strtoupper(Str::random(6)),
            'price' => 3500.00,
            'cost_price' => 1800.00,
            'stock_quantity' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_phase_1_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('back_in_stock_subscriptions'));
        $this->assertTrue(Schema::hasTable('back_in_stock_quarantined_records'));
        $this->assertTrue(Schema::hasTable('restock_events'));
        $this->assertTrue(Schema::hasTable('restock_event_claims'));
        $this->assertTrue(Schema::hasTable('subscription_active_leases'));
        $this->assertTrue(Schema::hasTable('back_in_stock_deliveries'));
        $this->assertTrue(Schema::hasTable('back_in_stock_notification_logs'));

        // Verify additive fields on back_in_stock_subscriptions
        $this->assertTrue(Schema::hasColumn('back_in_stock_subscriptions', 'locale'));
        $this->assertTrue(Schema::hasColumn('back_in_stock_subscriptions', 'verification_token_hash'));
        $this->assertTrue(Schema::hasColumn('back_in_stock_subscriptions', 'verification_expires_at'));
        $this->assertTrue(Schema::hasColumn('back_in_stock_subscriptions', 'verified_at'));

        // Verify additive fields on restock_event_claims
        $this->assertTrue(Schema::hasColumn('restock_event_claims', 'status'));
        $this->assertTrue(Schema::hasColumn('restock_event_claims', 'attempt_number'));
        $this->assertTrue(Schema::hasColumn('restock_event_claims', 'release_reason'));

        // Verify fields on subscription_active_leases
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'subscription_id'));
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'restock_event_id'));
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'claim_id'));
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'lease_owner'));
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'lease_token'));
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'lease_acquired_at'));
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'lease_expires_at'));
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'heartbeat_at'));
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'lease_version'));
        $this->assertTrue(Schema::hasColumn('subscription_active_leases', 'yield_count'));
    }

    public function test_guest_doi_fields_and_pending_verification_status(): void
    {
        $productId = $this->createTestProduct();
        $token = Str::random(32);
        $tokenHash = hash('sha256', $token);

        $sub = BackInStockSubscription::create([
            'product_id' => $productId,
            'email' => 'guest@aura.lux',
            'normalized_email' => 'guest@aura.lux',
            'locale' => 'en',
            'status' => BackInStockSubscription::STATUS_PENDING_VERIFICATION,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(32)),
            'verification_token_hash' => $tokenHash,
            'verification_expires_at' => now()->addHours(24),
        ]);

        $this->assertNotNull($sub->id);
        $this->assertEquals(BackInStockSubscription::STATUS_PENDING_VERIFICATION, $sub->status);
        $this->assertEquals('en', $sub->locale);
        $this->assertTrue($sub->is_active);
        $this->assertNull($sub->verified_at);

        // Verify unique constraint on verification_token_hash
        $this->expectException(QueryException::class);
        BackInStockSubscription::create([
            'product_id' => $productId,
            'email' => 'guest2@aura.lux',
            'normalized_email' => 'guest2@aura.lux',
            'locale' => 'si',
            'status' => BackInStockSubscription::STATUS_PENDING_VERIFICATION,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(32)),
            'verification_token_hash' => $tokenHash, // Duplicate token hash
            'verification_expires_at' => now()->addHours(24),
        ]);
    }

    public function test_subscription_active_lease_primary_key_prevents_duplicate_active_claims(): void
    {
        $productId = $this->createTestProduct();
        $sub = BackInStockSubscription::create([
            'product_id' => $productId,
            'email' => 'subscriber@aura.lux',
            'normalized_email' => 'subscriber@aura.lux',
            'status' => BackInStockSubscription::STATUS_PROCESSING,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(32)),
        ]);

        $event1 = RestockEvent::create([
            'product_id' => $productId,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        $event2 = RestockEvent::create([
            'product_id' => $productId,
            'previous_stock' => 0,
            'new_stock' => 5,
            'restocked_quantity' => 5,
            'batch_limit' => 20,
            'claimed_count' => 0,
            'status' => RestockEvent::STATUS_PENDING_CLAIMS,
        ]);

        $claim1 = RestockEventClaim::create([
            'restock_event_id' => $event1->id,
            'subscription_id' => $sub->id,
            'claim_order' => 1,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 1,
        ]);

        // Event 1 acquires lease
        $lease = SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event1->id,
            'claim_id' => $claim1->id,
            'lease_owner' => 'worker-01',
            'lease_token' => (string) Str::uuid(),
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
        ]);

        $this->assertEquals($sub->id, $lease->subscription_id);

        // Event 2 attempting to acquire active lease for SAME subscription must fail on PK
        $this->expectException(QueryException::class);
        SubscriptionActiveLease::create([
            'subscription_id' => $sub->id, // Duplicate PK!
            'restock_event_id' => $event2->id,
            'claim_id' => $claim1->id,
            'lease_owner' => 'worker-02',
            'lease_token' => (string) Str::uuid(),
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
        ]);
    }

    public function test_reclaiming_released_subscription_in_same_event_via_attempt_number(): void
    {
        $productId = $this->createTestProduct();
        $sub = BackInStockSubscription::create([
            'product_id' => $productId,
            'email' => 'reclaim@aura.lux',
            'normalized_email' => 'reclaim@aura.lux',
            'status' => BackInStockSubscription::STATUS_PENDING,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(32)),
        ]);

        $event = RestockEvent::create([
            'product_id' => $productId,
            'previous_stock' => 0,
            'new_stock' => 5,
            'restocked_quantity' => 5,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => RestockEvent::STATUS_PROCESSING,
        ]);

        // Attempt 1: claimed then released
        $claim1 = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 1,
            'status' => RestockEventClaim::STATUS_RELEASED,
            'attempt_number' => 1,
            'release_reason' => 'stock_depleted_before_dispatch',
        ]);

        $this->assertEquals(RestockEventClaim::STATUS_RELEASED, $claim1->status);

        // Attempt 2: Reclaimed within SAME event with incremented attempt_number
        $claim2 = RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 2,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 2,
        ]);

        $this->assertEquals(2, $claim2->attempt_number);

        // Duplicate attempt number 2 within SAME event must trigger unique constraint
        $this->expectException(QueryException::class);
        RestockEventClaim::create([
            'restock_event_id' => $event->id,
            'subscription_id' => $sub->id,
            'claim_order' => 3,
            'status' => RestockEventClaim::STATUS_CLAIMED,
            'attempt_number' => 2, // Duplicate attempt!
        ]);
    }

    public function test_durable_yield_count_atomic_increment(): void
    {
        $productId = $this->createTestProduct();
        $sub = BackInStockSubscription::create([
            'product_id' => $productId,
            'email' => 'yield@aura.lux',
            'normalized_email' => 'yield@aura.lux',
            'status' => BackInStockSubscription::STATUS_PROCESSING,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(32)),
        ]);

        $event = RestockEvent::create([
            'product_id' => $productId,
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

        $lease = SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event->id,
            'claim_id' => $claim->id,
            'lease_owner' => 'worker-01',
            'lease_token' => (string) Str::uuid(),
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
        ]);

        $this->assertEquals(0, $lease->yield_count);

        // Atomic update statement
        $nowExpr = DB::getDriverName() === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
        DB::statement("
            UPDATE subscription_active_leases 
            SET yield_count = yield_count + 1,
                updated_at = {$nowExpr}
            WHERE subscription_id = :id
        ", ['id' => $sub->id]);

        $freshLease = SubscriptionActiveLease::find($sub->id);
        $this->assertEquals(1, $freshLease->yield_count);
    }

    public function test_all_six_model_relationships(): void
    {
        $productId = $this->createTestProduct();
        $userId = $this->createTestUser();

        $sub = BackInStockSubscription::create([
            'product_id' => $productId,
            'customer_id' => $userId,
            'email' => 'full-rel@aura.lux',
            'normalized_email' => 'full-rel@aura.lux',
            'locale' => 'en',
            'status' => BackInStockSubscription::STATUS_PROCESSING,
            'is_active' => true,
            'unsubscribe_token_hash' => hash('sha256', Str::random(32)),
        ]);

        $event = RestockEvent::create([
            'product_id' => $productId,
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

        $lease = SubscriptionActiveLease::create([
            'subscription_id' => $sub->id,
            'restock_event_id' => $event->id,
            'claim_id' => $claim->id,
            'lease_owner' => 'rel-worker',
            'lease_token' => (string) Str::uuid(),
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
        ]);

        $delivery = BackInStockDelivery::create([
            'subscription_id' => $sub->id,
            'product_id' => $productId,
            'restock_event_id' => $event->id,
            'logical_delivery_key' => hash('sha256', "{$sub->id}_{$event->id}"),
            'status' => BackInStockDelivery::STATUS_INITIATED,
        ]);

        $log = BackInStockNotificationLog::create([
            'delivery_id' => $delivery->id,
            'attempt_number' => 1,
            'idempotency_key' => hash('sha256', "dispatch_{$delivery->logical_delivery_key}_1"),
            'status' => BackInStockNotificationLog::STATUS_INITIATED,
        ]);

        // Assert relationships traverse correctly
        $this->assertEquals($sub->id, $sub->product->id === $productId ? $sub->id : null);
        $this->assertEquals($userId, $sub->customer->id);
        $this->assertCount(1, $sub->claims);
        $this->assertEquals($lease->lease_token, $sub->activeLease->lease_token);
        $this->assertCount(1, $sub->deliveries);
        $this->assertEquals($event->id, $claim->restockEvent->id);
        $this->assertEquals($sub->id, $claim->subscription->id);
        $this->assertEquals($delivery->id, $log->delivery->id);
        $this->assertCount(1, $delivery->notificationLogs);
    }
}
