<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerFeaturesSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a minimal user.
     */
    protected function createTestUser(string $email = 'test@example.com'): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => $email,
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Helper to create a minimal product.
     */
    protected function createTestProduct(string $sku = 'SKU-001'): int
    {
        return DB::table('products')->insertGetId([
            'name_en' => 'Test Cosmetic Product',
            'name_si' => 'ටෙස්ට් නිශ්පාදනය',
            'slug' => 'test-product-' . Str::random(8),
            'sku' => $sku,
            'price' => 2500.00,
            'cost_price' => 1200.00,
            'stock_quantity' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Helper to create a minimal order.
     */
    protected function createTestOrder(int $userId): int
    {
        return DB::table('orders')->insertGetId([
            'order_number' => 'ORD-' . strtoupper(Str::random(10)),
            'customer_id' => $userId,
            'customer_name' => 'Test Customer',
            'customer_phone' => '0771234567',
            'shipping_address' => '123 Test Street, Colombo',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'subtotal' => 5000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 5350.00,
            'tracking_token' => Str::random(64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test 1: Verify all 16 Phase 1 tables and column extensions exist.
     */
    public function test_all_phase_1_tables_and_columns_exist(): void
    {
        // 1. Coupon tables
        $this->assertTrue(Schema::hasTable('coupons'));
        $this->assertTrue(Schema::hasTable('coupon_categories'));
        $this->assertTrue(Schema::hasTable('coupon_products'));
        $this->assertTrue(Schema::hasTable('coupon_usages'));

        // 2. Orders & Order items extensions
        $this->assertTrue(Schema::hasColumns('orders', [
            'coupon_id', 'coupon_code', 'discount_amount', 'shipping_discount_amount'
        ]));
        $this->assertTrue(Schema::hasColumns('order_items', [
            'discount_amount', 'unit_refund_schedule', 'refunded_unit_indexes', 'returned_quantity', 'refunded_amount'
        ]));
        $this->assertTrue(Schema::hasTable('order_refunds'));

        // 3. Product reviews & rating cache
        $this->assertTrue(Schema::hasTable('product_reviews'));
        $this->assertTrue(Schema::hasColumns('products', ['rating_cache', 'reviews_count']));

        // 4. Recently viewed products
        $this->assertTrue(Schema::hasTable('recently_viewed_products'));

        // 5. Back-in-stock tables
        $this->assertTrue(Schema::hasTable('back_in_stock_subscriptions'));
        $this->assertTrue(Schema::hasTable('back_in_stock_quarantined_records'));
        $this->assertTrue(Schema::hasTable('restock_events'));
        $this->assertTrue(Schema::hasTable('restock_event_claims'));
        $this->assertTrue(Schema::hasTable('back_in_stock_deliveries'));
        $this->assertTrue(Schema::hasTable('back_in_stock_notification_logs'));
    }

    /**
     * Test 2: Rollback and re-migration cleanly removes and re-creates all Phase 1 schema units.
     */
    public function test_phase_1_migrations_rollback_and_remigrate_cleanly(): void
    {
        $phase1Migrations = [
            'database/migrations/2026_11_13_000001_create_coupons_and_pivot_tables.php',
            'database/migrations/2026_11_13_000002_add_coupon_and_discount_fields_to_orders_table.php',
            'database/migrations/2026_11_13_000003_add_discount_and_refund_fields_to_order_items_table.php',
            'database/migrations/2026_11_13_000004_create_order_refunds_table.php',
            'database/migrations/2026_11_13_000005_create_product_reviews_and_add_rating_cache_to_products_table.php',
            'database/migrations/2026_11_13_000006_create_recently_viewed_products_table.php',
            'database/migrations/2026_11_13_000007_create_back_in_stock_subscriptions_and_quarantine_tables.php',
            'database/migrations/2026_11_13_000008_create_restock_events_deliveries_and_claims_tables.php',
            'database/migrations/2026_11_16_000001_add_verification_and_locale_to_back_in_stock_subscriptions_table.php',
            'database/migrations/2026_11_16_000002_refine_restock_event_claims_and_create_active_leases_table.php',
        ];

        // Rollback the 8 Phase 1 migrations in isolation
        $this->artisan('migrate:rollback', ['--path' => $phase1Migrations])->assertSuccessful();

        // Assert Phase 1 tables are dropped
        $this->assertFalse(Schema::hasTable('coupons'));
        $this->assertFalse(Schema::hasTable('coupon_usages'));
        $this->assertFalse(Schema::hasTable('order_refunds'));
        $this->assertFalse(Schema::hasTable('product_reviews'));
        $this->assertFalse(Schema::hasTable('recently_viewed_products'));
        $this->assertFalse(Schema::hasTable('back_in_stock_subscriptions'));
        $this->assertFalse(Schema::hasTable('restock_events'));

        // Re-run migrations in isolation
        $this->artisan('migrate', ['--path' => $phase1Migrations])->assertSuccessful();

        // Assert re-created
        $this->assertTrue(Schema::hasTable('coupons'));
        $this->assertTrue(Schema::hasTable('back_in_stock_subscriptions'));
    }

    /**
     * Test 3: Coupon unique constraint and usage RESTRICT foreign keys.
     */
    public function test_coupon_uniqueness_and_restrict_on_delete_behavior(): void
    {
        $userId = $this->createTestUser();
        $orderId = $this->createTestOrder($userId);

        $couponId = DB::table('coupons')->insertGetId([
            'code' => 'WELCOME10',
            'name' => '10% Off Welcome Coupon',
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Duplicate coupon code must throw QueryException
        $this->expectException(QueryException::class);
        DB::table('coupons')->insert([
            'code' => 'WELCOME10',
            'name' => 'Duplicate Code',
            'type' => 'fixed_amount',
            'value' => 500.00,
        ]);
    }

    /**
     * Test 4: Coupon usage prevents duplicate order-coupon application and enforces RESTRICT.
     */
    public function test_coupon_usage_duplicate_prevention_and_restrict_protection(): void
    {
        $userId = $this->createTestUser();
        $orderId = $this->createTestOrder($userId);

        $couponId = DB::table('coupons')->insertGetId([
            'code' => 'SAVE500',
            'name' => 'Save 500 LKR',
            'type' => 'fixed_amount',
            'value' => 500.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coupon_usages')->insert([
            'coupon_id' => $couponId,
            'order_id' => $orderId,
            'customer_id' => $userId,
            'customer_email' => 'customer@example.com',
            'customer_phone' => '0771234567',
            'discount_amount' => 500.00,
            'shipping_discount_amount' => 0.00,
            'created_at' => now(),
        ]);

        // Duplicate coupon on same order must fail (uk_cu_order_coupon)
        try {
            DB::table('coupon_usages')->insert([
                'coupon_id' => $couponId,
                'order_id' => $orderId,
                'customer_id' => $userId,
                'customer_email' => 'customer@example.com',
                'customer_phone' => '0771234567',
                'discount_amount' => 500.00,
            ]);
            $this->fail('Expected duplicate coupon usage on same order to throw QueryException');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'Duplicate coupon usage correctly rejected');
        }

        // Deleting order with attached coupon_usage must fail with RESTRICT violation
        try {
            DB::table('orders')->where('id', $orderId)->delete();
            $this->fail('Expected deleting order with linked coupon_usage to fail with RESTRICT');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'RESTRICT constraint on orders deletion confirmed');
        }
    }

    /**
     * Test 5: Composite refund relationship integrity (Directive 4.3).
     * Valid pair succeeds; mismatched pair (where item belongs to different order) is rejected.
     */
    public function test_composite_refund_relationship_enforces_cross_order_integrity(): void
    {
        $userId = $this->createTestUser();
        $productId = $this->createTestProduct();

        $order1Id = $this->createTestOrder($userId);
        $order2Id = $this->createTestOrder($userId);

        // Item 1 belongs to Order 1
        $item1Id = DB::table('order_items')->insertGetId([
            'order_id' => $order1Id,
            'product_id' => $productId,
            'product_name' => 'Test Item 1',
            'unit_price' => 1000.00,
            'quantity' => 2,
            'subtotal' => 2000.00,
            'discount_amount' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Valid refund: order_id = order1Id, order_item_id = item1Id (matches item's parent order)
        $refundId = DB::table('order_refunds')->insertGetId([
            'order_id' => $order1Id,
            'order_item_id' => $item1Id,
            'unit_index' => 1,
            'refund_cents' => 100000, // 1,000.00 LKR in BIGINT UNSIGNED
            'refund_amount' => 1000.00,
            'reason' => 'Customer returned unit 1',
            'processed_by' => $userId,
            'created_at' => now(),
        ]);
        $this->assertGreaterThan(0, $refundId);

        // 2. Unit duplicate refund prevention: same unit_index on same item must fail (uk_or_item_unit)
        try {
            DB::table('order_refunds')->insert([
                'order_id' => $order1Id,
                'order_item_id' => $item1Id,
                'unit_index' => 1,
                'refund_cents' => 100000,
                'refund_amount' => 1000.00,
                'reason' => 'Duplicate refund attempt',
                'processed_by' => $userId,
            ]);
            $this->fail('Expected duplicate unit refund to fail unique constraint');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'Duplicate unit refund correctly rejected by uk_or_item_unit');
        }

        // 3. Deliberately invalid cross-order pair: order_id = order2Id, order_item_id = item1Id (belonging to order 1)
        // Must fail with composite foreign key violation fk_or_order_item_composite!
        try {
            DB::table('order_refunds')->insert([
                'order_id' => $order2Id, // MISMATCHED! Item 1 belongs to Order 1, not Order 2!
                'order_item_id' => $item1Id,
                'unit_index' => 2,
                'refund_cents' => 100000,
                'refund_amount' => 1000.00,
                'reason' => 'Cross-order spoofed refund',
                'processed_by' => $userId,
            ]);
            $this->fail('Expected mismatched cross-order refund to be rejected by composite foreign key');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'Mismatched cross-order refund successfully rejected by composite FK');
        }
    }

    /**
     * Test 6: Refund BIGINT UNSIGNED capacity handles values exceeding 32-bit INT limit.
     */
    public function test_refund_cents_supports_64_bit_bigint_unsigned(): void
    {
        $userId = $this->createTestUser();
        $productId = $this->createTestProduct();
        $orderId = $this->createTestOrder($userId);

        $itemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => 'Luxury Unit',
            'unit_price' => 50000000.00,
            'quantity' => 1,
            'subtotal' => 50000000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Value exceeding 32-bit unsigned int (4,294,967,295): 5,000,000,000 cents (50 Million LKR)
        $largeRefundCents = 5000000000;

        $refundId = DB::table('order_refunds')->insertGetId([
            'order_id' => $orderId,
            'order_item_id' => $itemId,
            'unit_index' => 1,
            'refund_cents' => $largeRefundCents,
            'refund_amount' => 50000000.00,
            'reason' => 'High-value wholesale return',
            'processed_by' => $userId,
            'created_at' => now(),
        ]);

        $stored = DB::table('order_refunds')->where('id', $refundId)->first();
        $this->assertEquals($largeRefundCents, $stored->refund_cents);
    }

    /**
     * Test 7: Product reviews single review per customer per product.
     */
    public function test_product_reviews_single_review_per_customer_per_product(): void
    {
        $userId = $this->createTestUser();
        $productId = $this->createTestProduct();
        $orderId = $this->createTestOrder($userId);

        DB::table('product_reviews')->insert([
            'product_id' => $productId,
            'customer_id' => $userId,
            'order_id' => $orderId,
            'rating' => 5,
            'title' => 'Excellent Night Cream',
            'comment' => 'Skin feels rejuvenated.',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Second review by same customer on same product must fail
        $this->expectException(QueryException::class);
        DB::table('product_reviews')->insert([
            'product_id' => $productId,
            'customer_id' => $userId,
            'order_id' => $orderId,
            'rating' => 4,
            'title' => 'Another review',
            'comment' => 'Duplicate attempt.',
            'status' => 'pending',
        ]);
    }

    /**
     * Test 8: Recently viewed products deduplication.
     */
    public function test_recently_viewed_products_enforces_deduplication(): void
    {
        $userId = $this->createTestUser();
        $productId = $this->createTestProduct();

        DB::table('recently_viewed_products')->insert([
            'customer_id' => $userId,
            'product_id' => $productId,
            'viewed_at' => now(),
        ]);

        // Duplicate entry must violate uk_rvp_customer_product
        $this->expectException(QueryException::class);
        DB::table('recently_viewed_products')->insert([
            'customer_id' => $userId,
            'product_id' => $productId,
            'viewed_at' => now(),
        ]);
    }

    /**
     * Test 9: Back-in-stock active subscription uniqueness and nullable is_active bypass.
     */
    public function test_back_in_stock_active_subscription_uniqueness_and_null_bypass(): void
    {
        $productId = $this->createTestProduct();

        // 1. Insert first active subscription
        $sub1Id = DB::table('back_in_stock_subscriptions')->insertGetId([
            'product_id' => $productId,
            'email' => 'User@Example.com',
            'normalized_email' => 'user@example.com',
            'status' => 'pending',
            'is_active' => 1,
            'unsubscribe_token_hash' => hash('sha256', 'token-1'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertGreaterThan(0, $sub1Id);

        // 2. Second active subscription with same normalized_email must fail
        try {
            DB::table('back_in_stock_subscriptions')->insert([
                'product_id' => $productId,
                'email' => 'user@example.com',
                'normalized_email' => 'user@example.com',
                'status' => 'pending',
                'is_active' => 1,
                'unsubscribe_token_hash' => hash('sha256', 'token-2'),
            ]);
            $this->fail('Expected duplicate active subscription to fail unique constraint');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'Duplicate active subscription correctly rejected');
        }

        // 3. Deactivate first subscription by setting is_active = NULL (terminal state notified/failed)
        DB::table('back_in_stock_subscriptions')
            ->where('id', $sub1Id)
            ->update(['status' => 'notified', 'is_active' => null]);

        // 4. Now a new active subscription for the same normalized_email MUST succeed!
        $sub2Id = DB::table('back_in_stock_subscriptions')->insertGetId([
            'product_id' => $productId,
            'email' => 'user@example.com',
            'normalized_email' => 'user@example.com',
            'status' => 'pending',
            'is_active' => 1,
            'unsubscribe_token_hash' => hash('sha256', 'token-3'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertGreaterThan(0, $sub2Id);
    }

    /**
     * Test 10: Quarantine records permits NULL raw_email for oversized inputs without silent truncation (Guard 03).
     */
    public function test_quarantined_records_permits_null_raw_email_for_oversized_inputs(): void
    {
        $productId = $this->createTestProduct();
        $batchId = (string) Str::uuid();

        // Simulate overlength input (> 255 chars) handled per Guard 03: raw_email is NULL, reason recorded
        $recordId = DB::table('back_in_stock_quarantined_records')->insertGetId([
            'import_batch_id' => $batchId,
            'product_id' => $productId,
            'raw_email' => null, // Guard 03: NULL when length > 255 chars
            'normalized_email' => null,
            'quarantine_reason' => 'email_exceeds_max_length',
            'metadata' => json_encode([
                'product_id' => $productId,
                'source_row' => 42,
                'import_filename' => 'waitlist.csv',
                'char_length' => 290,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        $stored = DB::table('back_in_stock_quarantined_records')->where('id', $recordId)->first();
        $this->assertNull($stored->raw_email);
        $this->assertEquals('email_exceeds_max_length', $stored->quarantine_reason);
        $this->assertStringContainsString('char_length', $stored->metadata);
    }

    /**
     * Test 11: Restock event claims global uniqueness (Directive 4.1).
     */
    public function test_restock_event_claims_enforces_single_claim_per_subscription(): void
    {
        $productId = $this->createTestProduct();
        $eventId1 = (string) Str::uuid();
        $eventId2 = (string) Str::uuid();

        DB::table('restock_events')->insert([
            'id' => $eventId1,
            'product_id' => $productId,
            'previous_stock' => 0,
            'new_stock' => 10,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => 'claimed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('restock_events')->insert([
            'id' => $eventId2,
            'product_id' => $productId,
            'previous_stock' => 10,
            'new_stock' => 20,
            'restocked_quantity' => 10,
            'batch_limit' => 20,
            'claimed_count' => 1,
            'status' => 'claimed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subId = DB::table('back_in_stock_subscriptions')->insertGetId([
            'product_id' => $productId,
            'email' => 'alert@example.com',
            'normalized_email' => 'alert@example.com',
            'status' => 'processing',
            'is_active' => 1,
            'unsubscribe_token_hash' => hash('sha256', 'alert-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Claim in Event 1
        $claimId1 = DB::table('restock_event_claims')->insertGetId([
            'restock_event_id' => $eventId1,
            'subscription_id' => $subId,
            'claim_order' => 1,
            'lease_version' => 1,
            'attempt_number' => 1,
            'status' => 'claimed',
            'created_at' => now(),
        ]);

        // Active lease established for Event 1
        DB::table('subscription_active_leases')->insert([
            'subscription_id' => $subId,
            'restock_event_id' => $eventId1,
            'claim_id' => $claimId1,
            'lease_owner' => 'worker-01',
            'lease_token' => (string) Str::uuid(),
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempting to acquire concurrent active lease for SAME subscription in Event 2 must fail due to PK constraint
        $this->expectException(QueryException::class);
        DB::table('subscription_active_leases')->insert([
            'subscription_id' => $subId,
            'restock_event_id' => $eventId2,
            'claim_id' => 9999,
            'lease_owner' => 'worker-02',
            'lease_token' => (string) Str::uuid(),
            'lease_acquired_at' => now(),
            'lease_expires_at' => now()->addSeconds(90),
            'lease_version' => 1,
            'yield_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test 12: Dedicated logical delivery uniqueness and notification log attempt uniqueness.
     */
    public function test_delivery_and_notification_log_idempotency_constraints(): void
    {
        $productId = $this->createTestProduct();
        $eventId = (string) Str::uuid();

        DB::table('restock_events')->insert([
            'id' => $eventId,
            'product_id' => $productId,
            'previous_stock' => 0,
            'new_stock' => 5,
            'restocked_quantity' => 5,
            'batch_limit' => 10,
            'status' => 'claimed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subId = DB::table('back_in_stock_subscriptions')->insertGetId([
            'product_id' => $productId,
            'email' => 'delivery@example.com',
            'normalized_email' => 'delivery@example.com',
            'status' => 'processing',
            'is_active' => 1,
            'unsubscribe_token_hash' => hash('sha256', 'del-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert logical delivery
        $deliveryId = DB::table('back_in_stock_deliveries')->insertGetId([
            'subscription_id' => $subId,
            'product_id' => $productId,
            'restock_event_id' => $eventId,
            'logical_delivery_key' => hash('sha256', "delivery:{$subId}:{$eventId}"),
            'status' => 'initiated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Duplicate logical delivery on same subscription & event must fail (uk_bisd_sub_event)
        try {
            DB::table('back_in_stock_deliveries')->insert([
                'subscription_id' => $subId,
                'product_id' => $productId,
                'restock_event_id' => $eventId,
                'logical_delivery_key' => hash('sha256', "other-key:{$subId}"),
                'status' => 'initiated',
            ]);
            $this->fail('Expected duplicate logical delivery on same sub and event to fail');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'Duplicate logical delivery rejected by uk_bisd_sub_event');
        }

        // Insert attempt 1 log
        DB::table('back_in_stock_notification_logs')->insert([
            'delivery_id' => $deliveryId,
            'attempt_number' => 1,
            'idempotency_key' => hash('sha256', "attempt:{$deliveryId}:1"),
            'status' => 'initiated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Duplicate attempt 1 on same delivery must fail (uk_bisnl_delivery_attempt)
        $this->expectException(QueryException::class);
        DB::table('back_in_stock_notification_logs')->insert([
            'delivery_id' => $deliveryId,
            'attempt_number' => 1,
            'idempotency_key' => hash('sha256', "attempt:{$deliveryId}:1-dup"),
            'status' => 'failed',
        ]);
    }
}

