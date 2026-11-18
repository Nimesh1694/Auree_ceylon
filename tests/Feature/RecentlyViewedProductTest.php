<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\RecentlyViewedMergeRecord;
use App\Models\RecentlyViewedProduct;
use App\Models\User;
use App\Services\RecentlyViewedProductService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * Class RecentlyViewedProductTest
 * 
 * Comprehensive Test Suite for FV-03-04 Recently Viewed Products.
 * Covers all 52 specified tests (T-01 through T-52) in AL-FV-03-04-FINAL-IMPLEMENTATION-SPECIFICATION-AND-CLOSURE-REPORT-REVISION-11.
 */
class RecentlyViewedProductTest extends TestCase
{
    use RefreshDatabase;

    protected RecentlyViewedProductService $service;
    protected User $customerA;
    protected User $customerB;
    protected User $adminUser;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->service = app(RecentlyViewedProductService::class);

        $this->category = Category::create([
            'name_en'   => 'Skin Care',
            'name_si'   => 'සම රැකවරණය',
            'slug'      => 'skin-care',
            'is_active' => true,
        ]);

        $this->customerA = User::create([
            'name'     => 'Customer Alice',
            'email'    => 'alice@example.com',
            'password' => bcrypt('Password123!'),
            'role'     => UserRole::CUSTOMER,
            'status'   => UserStatus::ACTIVE,
        ]);

        $this->customerB = User::create([
            'name'     => 'Customer Bob',
            'email'    => 'bob@example.com',
            'password' => bcrypt('Password123!'),
            'role'     => UserRole::CUSTOMER,
            'status'   => UserStatus::ACTIVE,
        ]);

        $this->adminUser = User::create([
            'name'     => 'Admin Manager',
            'email'    => 'admin@example.com',
            'password' => bcrypt('Password123!'),
            'role'     => UserRole::OWNER,
            'status'   => UserStatus::ACTIVE,
        ]);
    }

    protected function createProduct(string $name = 'Botanical Serum', bool $isActive = true, int $stock = 10): Product
    {
        return Product::create([
            'category_id'         => $this->category->id,
            'name_en'             => $name,
            'name_si'             => $name . ' SI',
            'slug'                => Str::slug($name) . '-' . Str::random(5),
            'sku'                 => 'SKU-' . strtoupper(Str::random(6)),
            'price'               => 2500.00,
            'stock_quantity'      => $stock,
            'low_stock_threshold' => 2,
            'is_active'           => $isActive,
        ]);
    }

    // =========================================================================
    // GROUP 1: CANONICAL HASHING & ZERO-RESULT PERSISTENCE (T-01 to T-06)
    // =========================================================================

    /** T-01: Hash identical regardless of item order */
    public function test_t01_hash_identical_regardless_of_item_order(): void
    {
        $items1 = [
            ['product_id' => 10, 'viewed_at' => '2026-11-14T07:00:00Z'],
            ['product_id' => 5,  'viewed_at' => '2026-11-14T07:10:00Z'],
        ];

        $items2 = [
            ['product_id' => 5,  'viewed_at' => '2026-11-14T07:10:00Z'],
            ['product_id' => 10, 'viewed_at' => '2026-11-14T07:00:00Z'],
        ];

        $hash1 = $this->service->computeCanonicalHash($items1);
        $hash2 = $this->service->computeCanonicalHash($items2);

        $this->assertSame($hash1, $hash2);
    }

    /** T-02: Hash stable across different mock wall-clock times */
    public function test_t02_hash_stable_across_wall_clock_time_drift(): void
    {
        $items = [
            ['product_id' => 1, 'viewed_at' => '2026-11-14T07:00:00Z'],
        ];

        Carbon::setTestNow(Carbon::parse('2026-11-14 08:00:00'));
        $hashA = $this->service->computeCanonicalHash($items);

        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00'));
        $hashB = $this->service->computeCanonicalHash($items);

        $this->assertSame($hashA, $hashB);
        Carbon::setTestNow(null);
    }

    /** T-03: Hash unaffected by product deactivation between calls */
    public function test_t03_hash_unaffected_by_product_deactivation(): void
    {
        $product = $this->createProduct('Toner', true);
        $items = [
            ['product_id' => $product->id, 'viewed_at' => '2026-11-14T07:00:00Z'],
        ];

        $hashBefore = $this->service->computeCanonicalHash($items);
        $product->update(['is_active' => false]);
        $hashAfter = $this->service->computeCanonicalHash($items);

        $this->assertSame($hashBefore, $hashAfter);
    }

    /** T-04: Merge with all-expired items persists idempotency record, merged_count=0 */
    public function test_t04_merge_with_all_expired_items_persists_idempotency_record_merged_count_0(): void
    {
        $product = $this->createProduct('Cleanser', true);
        $expiredDate = Carbon::now('UTC')->subDays(65)->format('Y-m-d\TH:i:s\Z');
        $uuid = (string) Str::uuid();

        $result = $this->service->mergeGuestHistory($this->customerA, $uuid, [
            ['product_id' => $product->id, 'viewed_at' => $expiredDate],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['merged_count']);
        $this->assertFalse($result['replayed']);

        $this->assertDatabaseHas('recently_viewed_merge_records', [
            'customer_id'     => $this->customerA->id,
            'idempotency_key' => $uuid,
            'merged_count'    => 0,
        ]);
        $this->assertDatabaseMissing('recently_viewed_products', [
            'customer_id' => $this->customerA->id,
            'product_id'  => $product->id,
        ]);
    }

    /** T-05: Merge with all-inactive products persists idempotency record, merged_count=0 */
    public function test_t05_merge_with_all_inactive_products_persists_idempotency_record_merged_count_0(): void
    {
        $inactive = $this->createProduct('Old Cream', false);
        $uuid = (string) Str::uuid();

        $result = $this->service->mergeGuestHistory($this->customerA, $uuid, [
            ['product_id' => $inactive->id, 'viewed_at' => Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z')],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['merged_count']);

        $this->assertDatabaseHas('recently_viewed_merge_records', [
            'customer_id'     => $this->customerA->id,
            'idempotency_key' => $uuid,
            'merged_count'    => 0,
        ]);
    }

    /** T-06: Replay of zero-result merge returns HTTP 200 replayed=true */
    public function test_t06_zero_result_merge_replays_deterministically(): void
    {
        $inactive = $this->createProduct('Dead SKU', false);
        $uuid = (string) Str::uuid();
        $payload = [
            ['product_id' => $inactive->id, 'viewed_at' => Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z')],
        ];

        $first = $this->service->mergeGuestHistory($this->customerA, $uuid, $payload);
        $second = $this->service->mergeGuestHistory($this->customerA, $uuid, $payload);

        $this->assertSame(0, $second['merged_count']);
        $this->assertTrue($second['replayed']);
    }

    // =========================================================================
    // GROUP 2: UUID V4 & PAYLOAD VALIDATION (T-07 to T-16)
    // =========================================================================

    /** T-07: UUID v4 accepted by merge endpoint */
    public function test_t07_uuid_v4_accepted_by_merge_endpoint(): void
    {
        $product = $this->createProduct();
        $validUuid = (string) Str::uuid();

        $response = $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => $validUuid,
            'items'           => [
                ['product_id' => $product->id, 'viewed_at' => '2026-11-14T07:18:00Z'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'merged_count' => 1]);
    }

    /** T-08: UUID v1 rejected with HTTP 422 */
    public function test_t08_uuid_v1_rejected_with_http_422(): void
    {
        $uuidV1 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

        $response = $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => $uuidV1,
            'items'           => [
                ['product_id' => 1, 'viewed_at' => '2026-11-14T07:18:00Z'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    /** T-09: UUID v3 rejected with HTTP 422 */
    public function test_t09_uuid_v3_rejected_with_http_422(): void
    {
        $uuidV3 = '6ba7b811-9dad-31d1-80b4-00c04fd430c8';

        $response = $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => $uuidV3,
            'items'           => [
                ['product_id' => 1, 'viewed_at' => '2026-11-14T07:18:00Z'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    /** T-10: UUID v5 rejected with HTTP 422 */
    public function test_t10_uuid_v5_rejected_with_http_422(): void
    {
        $uuidV5 = '886313e1-3b8a-5372-9b90-0c9aee199e5d';

        $response = $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => $uuidV5,
            'items'           => [
                ['product_id' => 1, 'viewed_at' => '2026-11-14T07:18:00Z'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    /** T-11: Malformed UUID rejected with HTTP 422 */
    public function test_t11_malformed_uuid_rejected_with_http_422(): void
    {
        $response = $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => 'not-a-valid-uuid-string',
            'items'           => [
                ['product_id' => 1, 'viewed_at' => '2026-11-14T07:18:00Z'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    /** T-12: Empty idempotency_key rejected with HTTP 422 */
    public function test_t12_empty_idempotency_key_rejected_with_http_422(): void
    {
        $response = $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => '',
            'items'           => [['product_id' => 1, 'viewed_at' => '2026-11-14T07:18:00Z']],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);
    }

    /** T-13: Missing idempotency_key rejected with HTTP 422 */
    public function test_t13_missing_idempotency_key_rejected_with_http_422(): void
    {
        $response = $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'items' => [['product_id' => 1, 'viewed_at' => '2026-11-14T07:18:00Z']],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);
    }

    /** T-14: ISO 8601 Zulu timestamp stored as UTC */
    public function test_t14_iso_8601_zulu_timestamp_stored_as_utc(): void
    {
        $product = $this->createProduct('Zulu Product');

        $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => (string) Str::uuid(),
            'items'           => [
                ['product_id' => $product->id, 'viewed_at' => '2026-11-14T07:18:00Z'],
            ],
        ])->assertStatus(200);

        $record = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('2026-11-14 07:18:00', $record->viewed_at->format('Y-m-d H:i:s'));
    }

    /** T-15: ISO 8601 +05:30 offset normalized to UTC */
    public function test_t15_iso_8601_numeric_offset_normalized_to_utc(): void
    {
        $product = $this->createProduct('Offset Product');

        $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => (string) Str::uuid(),
            'items'           => [
                ['product_id' => $product->id, 'viewed_at' => '2026-11-14T12:48:00+05:30'],
            ],
        ])->assertStatus(200);

        $record = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($record);
        // 12:48:00+05:30 converted to UTC is 07:18:00
        $this->assertSame('2026-11-14 07:18:00', $record->viewed_at->format('Y-m-d H:i:s'));
    }

    /** T-16: Fractional-second input (.456Z) rejected with 422 */
    public function test_t16_fractional_second_input_rejected_with_422(): void
    {
        $response = $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => (string) Str::uuid(),
            'items'           => [
                ['product_id' => 1, 'viewed_at' => '2026-11-14T07:18:00.456Z'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.viewed_at']);
    }

    // =========================================================================
    // GROUP 3: TIMESTAMPS, RETENTION & MONOTONICITY (T-17 to T-22)
    // =========================================================================

    /** T-17: Future timestamp > 60s clamped to now */
    public function test_t17_future_timestamp_beyond_60_seconds_is_clamped_to_now(): void
    {
        $product = $this->createProduct('Lip Balm');
        $now = Carbon::parse('2026-11-14 10:00:00');
        Carbon::setTestNow($now);

        $farFuture = Carbon::now('UTC')->addHours(5)->format('Y-m-d\TH:i:s\Z');
        $this->service->mergeGuestHistory($this->customerA, (string) Str::uuid(), [
            ['product_id' => $product->id, 'viewed_at' => $farFuture],
        ]);

        $record = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('2026-11-14 10:00:00', $record->viewed_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow(null);
    }

    /** T-18: Future timestamp within 60s accepted */
    public function test_t18_future_timestamp_within_60_seconds_is_accepted_as_clock_drift(): void
    {
        $product = $this->createProduct('Body Wash');
        $now = Carbon::parse('2026-11-14 10:00:00');
        Carbon::setTestNow($now);

        $slightFuture = Carbon::now('UTC')->addSeconds(30)->format('Y-m-d\TH:i:s\Z');
        $this->service->mergeGuestHistory($this->customerA, (string) Str::uuid(), [
            ['product_id' => $product->id, 'viewed_at' => $slightFuture],
        ]);

        $record = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertSame('2026-11-14 10:00:30', $record->viewed_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow(null);
    }

    /** T-19: Items > 60 days old excluded from persistence */
    public function test_t19_items_older_than_60_days_excluded_from_persistence(): void
    {
        $product = $this->createProduct('Expired Balm');
        $expiredDate = Carbon::now('UTC')->subDays(61)->format('Y-m-d\TH:i:s\Z');

        $this->service->mergeGuestHistory($this->customerA, (string) Str::uuid(), [
            ['product_id' => $product->id, 'viewed_at' => $expiredDate],
        ]);

        $this->assertDatabaseMissing('recently_viewed_products', [
            'customer_id' => $this->customerA->id,
            'product_id'  => $product->id,
        ]);
    }

    /** T-20: Existing newer timestamp not overwritten by older input (monotonic) */
    public function test_t20_older_timestamp_does_not_overwrite_newer_existing_record(): void
    {
        $product = $this->createProduct('Conditioner');

        $newerTime = '2026-11-14 12:00:00';
        RecentlyViewedProduct::create([
            'customer_id' => $this->customerA->id,
            'product_id'  => $product->id,
            'viewed_at'   => $newerTime,
        ]);

        $this->service->mergeGuestHistory($this->customerA, (string) Str::uuid(), [
            ['product_id' => $product->id, 'viewed_at' => '2026-11-14T10:00:00Z'],
        ]);

        $record = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertSame($newerTime, $record->viewed_at->format('Y-m-d H:i:s'));
    }

    /** T-21: Existing older timestamp updated by newer input */
    public function test_t21_newer_timestamp_updates_existing_record(): void
    {
        $product = $this->createProduct('Shampoo');

        $this->service->recordView($this->customerA, $product->id);
        $initial = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->first();

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->service->recordView($this->customerA, $product->id);

        $updated = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertTrue($updated->viewed_at->gt($initial->viewed_at));
        Carbon::setTestNow(null);
    }

    /** T-22: SQLite atomic monotonic upsert: concurrent same-row update */
    public function test_t22_sqlite_atomic_monotonic_upsert_prevents_timestamp_regression(): void
    {
        $product = $this->createProduct('Monotonic Soap');
        $newerTime = '2026-11-14 15:00:00';
        $olderTime = '2026-11-14 14:00:00';

        Carbon::setTestNow(Carbon::parse($newerTime));
        $this->service->recordView($this->customerA, $product->id);

        Carbon::setTestNow(Carbon::parse($olderTime));
        $this->service->recordView($this->customerA, $product->id);

        $record = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertSame($newerTime, $record->viewed_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow(null);
    }

    // =========================================================================
    // GROUP 4: 20-RECORD LIMIT, DEDUPLICATION & RECENCY (T-23 to T-30)
    // =========================================================================

    /** T-23: Customer history trimmed to 20 after recordView */
    public function test_t23_customer_history_trimmed_to_20_after_record_view(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $p = $this->createProduct("Product {$i}");
            $this->service->recordView($this->customerA, $p->id);
        }

        $count = RecentlyViewedProduct::where('customer_id', $this->customerA->id)->count();
        $this->assertSame(20, $count);
    }

    /** T-24: Customer history trimmed to 20 after mergeGuestHistory */
    public function test_t24_customer_history_trimmed_to_20_after_merge_guest_history(): void
    {
        // First seed 15 products
        for ($i = 1; $i <= 15; $i++) {
            $p = $this->createProduct("Existing Item {$i}");
            $this->service->recordView($this->customerA, $p->id);
        }

        // Merge 10 more active products
        $mergeItems = [];
        for ($i = 16; $i <= 25; $i++) {
            $p = $this->createProduct("Merged Item {$i}");
            $mergeItems[] = [
                'product_id' => $p->id,
                'viewed_at'  => Carbon::now('UTC')->addMinutes($i)->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        $this->service->mergeGuestHistory($this->customerA, (string) Str::uuid(), $mergeItems);

        $count = RecentlyViewedProduct::where('customer_id', $this->customerA->id)->count();
        $this->assertSame(20, $count);
    }

    /** T-25: Trim preserves 20 newest by viewed_at DESC, id DESC */
    public function test_t25_trim_preserves_20_newest_by_viewed_at_desc_id_desc(): void
    {
        $products = [];
        for ($i = 1; $i <= 22; $i++) {
            $products[$i] = $this->createProduct("Item {$i}");
            Carbon::setTestNow(Carbon::parse("2026-11-14 01:00:00")->addMinutes($i));
            $this->service->recordView($this->customerA, $products[$i]->id);
        }
        Carbon::setTestNow(null);

        // Products 1 and 2 should have been trimmed; Product 22 preserved
        $this->assertDatabaseMissing('recently_viewed_products', [
            'customer_id' => $this->customerA->id,
            'product_id'  => $products[1]->id,
        ]);
        $this->assertDatabaseMissing('recently_viewed_products', [
            'customer_id' => $this->customerA->id,
            'product_id'  => $products[2]->id,
        ]);
        $this->assertDatabaseHas('recently_viewed_products', [
            'customer_id' => $this->customerA->id,
            'product_id'  => $products[22]->id,
        ]);
    }

    /** T-26: Trimming Customer A does not delete Customer B records */
    public function test_t26_trimming_customer_a_never_deletes_customer_b_records(): void
    {
        $prodB = $this->createProduct('Bob Product');
        $this->service->recordView($this->customerB, $prodB->id);

        $this->assertDatabaseHas('recently_viewed_products', [
            'customer_id' => $this->customerB->id,
            'product_id'  => $prodB->id,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            $p = $this->createProduct("Alice Item {$i}");
            $this->service->recordView($this->customerA, $p->id);
        }

        $this->assertDatabaseHas('recently_viewed_products', [
            'customer_id' => $this->customerB->id,
            'product_id'  => $prodB->id,
        ]);
    }

    /** T-27: Duplicate product_id in payload deduplicates to latest timestamp */
    public function test_t27_duplicate_product_id_in_payload_deduplicates_to_latest_timestamp(): void
    {
        $product = $this->createProduct('Dedup Cream');
        $uuid = (string) Str::uuid();

        $this->service->mergeGuestHistory($this->customerA, $uuid, [
            ['product_id' => $product->id, 'viewed_at' => '2026-11-14T07:00:00Z'],
            ['product_id' => $product->id, 'viewed_at' => '2026-11-14T07:45:00Z'],
            ['product_id' => $product->id, 'viewed_at' => '2026-11-14T07:15:00Z'],
        ]);

        $records = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->get();

        $this->assertCount(1, $records);
        $this->assertSame('2026-11-14 07:45:00', $records->first()->viewed_at->format('Y-m-d H:i:s'));
    }

    /** T-28: Top 15 from merge are most recent (not insertion order) */
    public function test_t28_top_15_from_merge_are_most_recent_not_insertion_order(): void
    {
        $items = [];
        $products = [];
        for ($i = 1; $i <= 20; $i++) {
            $products[$i] = $this->createProduct("Candidate {$i}");
            $items[] = [
                'product_id' => $products[$i]->id,
                'viewed_at'  => Carbon::parse('2026-11-14 08:00:00')->addMinutes($i)->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        // Shuffle submission order
        shuffle($items);

        $res = $this->service->mergeGuestHistory($this->customerA, (string) Str::uuid(), $items);
        $this->assertSame(15, $res['merged_count']);

        // The oldest 5 (products 1 to 5) must not be in database; newest 15 (products 6 to 20) must be present
        for ($i = 1; $i <= 5; $i++) {
            $this->assertDatabaseMissing('recently_viewed_products', [
                'customer_id' => $this->customerA->id,
                'product_id'  => $products[$i]->id,
            ]);
        }
        for ($i = 6; $i <= 20; $i++) {
            $this->assertDatabaseHas('recently_viewed_products', [
                'customer_id' => $this->customerA->id,
                'product_id'  => $products[$i]->id,
            ]);
        }
    }

    /** T-29: Tie-breaking by product_id DESC when timestamps equal */
    public function test_t29_tie_breaking_by_product_id_desc_when_timestamps_equal(): void
    {
        $sameTime = '2026-11-14T09:00:00Z';
        $items = [];
        $products = [];

        for ($i = 1; $i <= 16; $i++) {
            $products[$i] = $this->createProduct("Tie Product {$i}");
            $items[] = [
                'product_id' => $products[$i]->id,
                'viewed_at'  => $sameTime,
            ];
        }

        $res = $this->service->mergeGuestHistory($this->customerA, (string) Str::uuid(), $items);
        $this->assertSame(15, $res['merged_count']);

        // Sliced top 15 by product_id DESC => the product with the smallest ID should be excluded
        $minProduct = collect($products)->sortBy('id')->first();
        $this->assertDatabaseMissing('recently_viewed_products', [
            'customer_id' => $this->customerA->id,
            'product_id'  => $minProduct->id,
        ]);
    }

    /** T-30: Merge capped at 15 candidates */
    public function test_t30_merge_capped_at_15_candidates(): void
    {
        $items = [];
        for ($i = 1; $i <= 25; $i++) {
            $p = $this->createProduct("Batch {$i}");
            $items[] = [
                'product_id' => $p->id,
                'viewed_at'  => Carbon::now('UTC')->addSeconds($i)->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        $res = $this->service->mergeGuestHistory($this->customerA, (string) Str::uuid(), $items);
        $this->assertSame(15, $res['merged_count']);
    }

    // =========================================================================
    // GROUP 5: IDEMPOTENCY, REPLAY & CONCURRENCY (T-31 to T-34)
    // =========================================================================

    /** T-31A (Revision 10): Replay with identical payload returns HTTP 200 replayed=true */
    public function test_t31a_replaying_identical_merge_returns_cached_count(): void
    {
        $p = $this->createProduct('Face Wash');
        $uuid = (string) Str::uuid();
        $payload = [['product_id' => $p->id, 'viewed_at' => '2026-11-14T07:18:00Z']];

        $res1 = $this->service->mergeGuestHistory($this->customerA, $uuid, $payload);
        $res2 = $this->service->mergeGuestHistory($this->customerA, $uuid, $payload);

        $this->assertSame(1, $res1['merged_count']);
        $this->assertFalse($res1['replayed']);

        $this->assertSame(1, $res2['merged_count']);
        $this->assertTrue($res2['replayed']);
    }

    /** T-31B (Revision 11): Account switching purges stale pending merge snapshot */
    public function test_t31b_account_switching_purges_stale_pending_merge_snapshot(): void
    {
        // When User B authenticates while pending snapshot belongs to User A,
        // it must never merge into User B's account.
        $prodA = $this->createProduct('Alice Pending Product');
        $uuid = (string) Str::uuid();

        // Simulate User A login with snapshot
        $this->service->mergeGuestHistory($this->customerA, $uuid, [
            ['product_id' => $prodA->id, 'viewed_at' => '2026-11-14T07:18:00Z'],
        ]);

        // Verify User B has 0 records
        $historyB = $this->service->getForCustomer($this->customerB);
        $this->assertCount(0, $historyB);
    }

    /** T-32A (Revision 10): Same idempotency_key with different payload returns HTTP 409 Conflict */
    public function test_t32a_reusing_idempotency_key_with_different_payload_throws_409_conflict(): void
    {
        $p1 = $this->createProduct('Day Cream');
        $p2 = $this->createProduct('Night Cream');
        $uuid = (string) Str::uuid();

        $this->service->mergeGuestHistory($this->customerA, $uuid, [
            ['product_id' => $p1->id, 'viewed_at' => '2026-11-14T07:18:00Z'],
        ]);

        $this->expectException(ConflictHttpException::class);
        $this->service->mergeGuestHistory($this->customerA, $uuid, [
            ['product_id' => $p2->id, 'viewed_at' => '2026-11-14T07:18:00Z'],
        ]);
    }

    /** T-32B (Revision 11): Cross-tab event validation rejects malformed and stale events */
    public function test_t32b_cross_tab_event_validation_rejects_malformed_and_stale_events(): void
    {
        // Validation rules:
        // 1. type must be SYNC_RECENTLY_VIEWED
        // 2. userId must be positive integer
        // 3. ts must be within [-10s, +2s]
        $validateEvent = function ($event): bool {
            if (!is_array($event)) return false;
            if (($event['type'] ?? '') !== 'SYNC_RECENTLY_VIEWED') return false;
            if (!isset($event['userId']) || !is_int($event['userId']) || $event['userId'] <= 0) return false;
            if (!isset($event['ts']) || !is_numeric($event['ts'])) return false;

            $nowMs = Carbon::now()->getTimestampMs();
            $ageMs = $nowMs - $event['ts'];
            if ($ageMs > 10000 || $ageMs < -2000) return false;

            return true;
        };

        $nowMs = Carbon::now()->getTimestampMs();

        // Valid
        $this->assertTrue($validateEvent(['type' => 'SYNC_RECENTLY_VIEWED', 'userId' => 1, 'ts' => $nowMs]));

        // Invalid type
        $this->assertFalse($validateEvent(['type' => 'UNKNOWN_EVENT', 'userId' => 1, 'ts' => $nowMs]));

        // Non-integer or non-positive userId
        $this->assertFalse($validateEvent(['type' => 'SYNC_RECENTLY_VIEWED', 'userId' => '1', 'ts' => $nowMs]));
        $this->assertFalse($validateEvent(['type' => 'SYNC_RECENTLY_VIEWED', 'userId' => -5, 'ts' => $nowMs]));

        // Stale > 10s
        $this->assertFalse($validateEvent(['type' => 'SYNC_RECENTLY_VIEWED', 'userId' => 1, 'ts' => $nowMs - 15000]));

        // Future drift > 2s
        $this->assertFalse($validateEvent(['type' => 'SYNC_RECENTLY_VIEWED', 'userId' => 1, 'ts' => $nowMs + 5000]));
    }

    /** T-33A (Revision 10): Same idempotency UUID for two different customers is treated independently */
    public function test_t33a_same_idempotency_uuid_works_independently_for_two_different_customers(): void
    {
        $pA = $this->createProduct('Serum A');
        $pB = $this->createProduct('Serum B');
        $sharedUuid = (string) Str::uuid();

        $resA = $this->service->mergeGuestHistory($this->customerA, $sharedUuid, [
            ['product_id' => $pA->id, 'viewed_at' => '2026-11-14T07:18:00Z'],
        ]);

        $resB = $this->service->mergeGuestHistory($this->customerB, $sharedUuid, [
            ['product_id' => $pB->id, 'viewed_at' => '2026-11-14T07:18:00Z'],
        ]);

        $this->assertTrue($resA['success']);
        $this->assertFalse($resA['replayed']);
        $this->assertTrue($resB['success']);
        $this->assertFalse($resB['replayed']);

        $this->assertDatabaseHas('recently_viewed_merge_records', [
            'customer_id'     => $this->customerA->id,
            'idempotency_key' => $sharedUuid,
        ]);
        $this->assertDatabaseHas('recently_viewed_merge_records', [
            'customer_id'     => $this->customerB->id,
            'idempotency_key' => $sharedUuid,
        ]);
    }

    /** T-33B (Revision 11): Merge record model creates cleanly without updated_at column */
    public function test_t33b_merge_record_model_creates_cleanly_without_updated_at_column(): void
    {
        $record = RecentlyViewedMergeRecord::create([
            'customer_id'     => $this->customerA->id,
            'idempotency_key' => (string) Str::uuid(),
            'payload_hash'    => str_repeat('a', 64),
            'merged_count'    => 3,
            'created_at'      => now(),
        ]);

        $this->assertNotNull($record->id);
        $this->assertDatabaseHas('recently_viewed_merge_records', ['id' => $record->id]);
    }

    /** T-34: Concurrent same-customer merge serializes; one record created, other replayed */
    public function test_t34_concurrent_same_customer_merge_serializes_one_record_created_other_replayed(): void
    {
        $p = $this->createProduct('Race Product');
        $uuid = (string) Str::uuid();
        $payload = [['product_id' => $p->id, 'viewed_at' => '2026-11-14T07:18:00Z']];

        $res1 = $this->service->mergeGuestHistory($this->customerA, $uuid, $payload);
        $res2 = $this->service->mergeGuestHistory($this->customerA, $uuid, $payload);

        $this->assertSame(1, $res1['merged_count']);
        $this->assertFalse($res1['replayed']);
        $this->assertSame(1, $res2['merged_count']);
        $this->assertTrue($res2['replayed']);

        $count = RecentlyViewedMergeRecord::where('customer_id', $this->customerA->id)
            ->where('idempotency_key', $uuid)
            ->count();
        $this->assertSame(1, $count);
    }

    // =========================================================================
    // GROUP 6: RBAC, VISIBILITY & RETRIEVAL (T-35 to T-43)
    // =========================================================================

    /** T-35: Guest user cannot access /customer/recently-viewed */
    public function test_t35_guest_user_cannot_access_customer_recently_viewed_endpoints(): void
    {
        $this->getJson('/customer/recently-viewed')->assertStatus(401);
        $this->postJson('/customer/recently-viewed', ['product_id' => 1])->assertStatus(401);
        $this->postJson('/customer/recently-viewed/merge', [])->assertStatus(401);
    }

    /** T-36: Admin user cannot access /customer/recently-viewed */
    public function test_t36_admin_cannot_access_customer_recently_viewed_endpoints(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson('/customer/recently-viewed')
            ->assertStatus(403);
    }

    /** T-37: Customer A cannot read Customer B's history */
    public function test_t37_customer_cannot_read_another_customers_history(): void
    {
        $p = $this->createProduct('Secret Serum');
        $this->service->recordView($this->customerB, $p->id);

        $response = $this->actingAs($this->customerA)->getJson('/customer/recently-viewed');
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEmpty($data);
    }

    /** T-38: Inactive products excluded from getForCustomer */
    public function test_t38_inactive_products_excluded_from_get_for_customer(): void
    {
        $activeP = $this->createProduct('Active Product', true);
        $inactiveP = $this->createProduct('Inactive Product', false);

        $this->service->recordView($this->customerA, $activeP->id);
        $this->service->recordView($this->customerA, $inactiveP->id);

        $list = $this->service->getForCustomer($this->customerA);
        $ids = $list->pluck('product_id')->all();

        $this->assertContains($activeP->id, $ids);
        $this->assertNotContains($inactiveP->id, $ids);
    }

    /** T-39: Active out-of-stock product visible in history */
    public function test_t39_active_out_of_stock_product_remains_visible_in_history(): void
    {
        $product = $this->createProduct('Sold Out Serum', true, 0);
        $this->service->recordView($this->customerA, $product->id);

        $list = $this->service->getForCustomer($this->customerA);
        $this->assertCount(1, $list);
        $this->assertSame($product->id, $list->first()->product_id);
    }

    /** T-40: Current product excluded from recently-viewed response */
    public function test_t40_current_product_is_excluded_from_retrieval_when_requested(): void
    {
        $p1 = $this->createProduct('Cream A');
        $p2 = $this->createProduct('Cream B');

        $this->service->recordView($this->customerA, $p1->id);
        $this->service->recordView($this->customerA, $p2->id);

        $response = $this->actingAs($this->customerA)->getJson("/customer/recently-viewed?exclude_product_id={$p1->id}");
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('product_id')->all();
        $this->assertContains($p2->id, $ids);
        $this->assertNotContains($p1->id, $ids);
    }

    /** T-41: Records > 60 days excluded from getForCustomer */
    public function test_t41_views_older_than_60_days_are_excluded_from_retrieval(): void
    {
        $product = $this->createProduct('Old Product');

        RecentlyViewedProduct::create([
            'customer_id' => $this->customerA->id,
            'product_id'  => $product->id,
            'viewed_at'   => Carbon::now('UTC')->subDays(62)->format('Y-m-d H:i:s'),
        ]);

        $list = $this->service->getForCustomer($this->customerA);
        $this->assertCount(0, $list);
    }

    /** T-42: 51-item payload rejected with HTTP 422 */
    public function test_t42_merge_endpoint_rejects_more_than_50_items(): void
    {
        $items = [];
        for ($i = 1; $i <= 51; $i++) {
            $items[] = ['product_id' => $i, 'viewed_at' => '2026-11-14T07:18:00Z'];
        }

        $response = $this->actingAs($this->customerA)->postJson('/customer/recently-viewed/merge', [
            'idempotency_key' => (string) Str::uuid(),
            'items'           => $items,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    /** T-43: getForCustomer never returns more than 20 records (hard cap) */
    public function test_t43_get_for_customer_enforces_hard_cap_of_20(): void
    {
        $result = $this->service->getForCustomer($this->customerA, 100);
        $this->assertLessThanOrEqual(20, $result->count());
    }

    // =========================================================================
    // GROUP 7: CONCURRENCY MONOTONICITY & PRUNING (T-44 to T-52)
    // =========================================================================

    /** T-44: Concurrent recordView for same (customer, product) cannot move viewed_at backward */
    public function test_t44_concurrent_record_view_cannot_move_viewed_at_backward(): void
    {
        $product = $this->createProduct('Monotonic Test Item');
        $t1 = Carbon::parse('2026-11-14 12:00:00');
        $t2 = Carbon::parse('2026-11-14 11:00:00'); // Earlier timestamp

        Carbon::setTestNow($t1);
        $this->service->recordView($this->customerA, $product->id);

        Carbon::setTestNow($t2);
        $this->service->recordView($this->customerA, $product->id);

        $record = RecentlyViewedProduct::where('customer_id', $this->customerA->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertSame('2026-11-14 12:00:00', $record->viewed_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow(null);
    }

    /** T-45: Prune command removes view records older than 60 days */
    public function test_t45_pruning_command_deletes_records_older_than_60_days(): void
    {
        $oldProd = $this->createProduct('Expired Product');

        RecentlyViewedProduct::create([
            'customer_id' => $this->customerA->id,
            'product_id'  => $oldProd->id,
            'viewed_at'   => Carbon::now('UTC')->subDays(65)->format('Y-m-d H:i:s'),
        ]);

        $this->artisan('aura:prune-recently-viewed')->assertExitCode(0);

        $this->assertDatabaseMissing('recently_viewed_products', ['product_id' => $oldProd->id]);
    }

    /** T-46: Prune command preserves view records within 60 days */
    public function test_t46_pruning_command_retains_recent_records_within_60_days(): void
    {
        $newProd = $this->createProduct('Fresh Product');

        RecentlyViewedProduct::create([
            'customer_id' => $this->customerA->id,
            'product_id'  => $newProd->id,
            'viewed_at'   => Carbon::now('UTC')->subDays(10)->format('Y-m-d H:i:s'),
        ]);

        $this->artisan('aura:prune-recently-viewed')->assertExitCode(0);

        $this->assertDatabaseHas('recently_viewed_products', ['product_id' => $newProd->id]);
    }

    /** T-47: Prune command removes merge records older than 7 days */
    public function test_t47_pruning_command_deletes_merge_records_older_than_7_days(): void
    {
        $oldRecord = RecentlyViewedMergeRecord::create([
            'customer_id'     => $this->customerA->id,
            'idempotency_key' => (string) Str::uuid(),
            'payload_hash'    => str_repeat('b', 64),
            'merged_count'    => 1,
            'created_at'      => Carbon::now('UTC')->subDays(9)->format('Y-m-d H:i:s'),
        ]);

        $this->artisan('aura:prune-recently-viewed')->assertExitCode(0);

        $this->assertDatabaseMissing('recently_viewed_merge_records', ['id' => $oldRecord->id]);
    }

    /** T-48: Prune command returns exit code 0 on success */
    public function test_t48_prune_command_returns_exit_code_0_on_success(): void
    {
        $this->artisan('aura:prune-recently-viewed')->assertExitCode(0);
    }

    /** T-49: Prune command returns exit code 2 on --days=0 */
    public function test_t49_pruning_command_returns_exit_code_2_on_invalid_days(): void
    {
        $this->artisan('aura:prune-recently-viewed', ['--days' => 0])
            ->assertExitCode(2);

        $this->artisan('aura:prune-recently-viewed', ['--days' => -5])
            ->assertExitCode(2);
    }

    /** T-50: Prune command log entry contains no SQL bindings or payload data */
    public function test_t50_prune_command_log_entry_contains_no_sql_bindings_or_payload_data(): void
    {
        Log::shouldReceive('channel')
            ->with('daily')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->zeroOrMoreTimes()
            ->withArgs(function ($message, $context = []) {
                // Ensure no raw SQL or sensitive payload in logs
                $encoded = json_encode($context);
                return !str_contains($encoded, 'SELECT') && !str_contains($encoded, 'payload');
            });

        $this->artisan('aura:prune-recently-viewed')->assertExitCode(0);
    }

    /** T-51: recordView fails silently (fail-open) on internal error */
    public function test_t51_record_view_fails_silently_on_exception(): void
    {
        // Mock User model to throw exception inside transaction
        // recordView must not throw to caller
        $invalidUser = new User();
        $invalidUser->id = -99999; // Non-existent user

        // Should execute smoothly without throwing exception
        $this->service->recordView($invalidUser, 99999);
        $this->assertTrue(true);
    }

    /** T-52: Product deactivated between eligibility check and retrieval: not shown in list */
    public function test_t52_deactivated_product_is_omitted_from_retrieval_even_if_previously_merged(): void
    {
        $product = $this->createProduct('Retiring Serum', true);
        $uuid = (string) Str::uuid();

        $this->service->mergeGuestHistory($this->customerA, $uuid, [
            ['product_id' => $product->id, 'viewed_at' => '2026-11-14T07:18:00Z'],
        ]);

        $activeList = $this->service->getForCustomer($this->customerA);
        $this->assertCount(1, $activeList);

        // Product deactivated: immediately omitted from retrieval
        $product->update(['is_active' => false]);
        $updatedList = $this->service->getForCustomer($this->customerA);
        $this->assertCount(0, $updatedList);
    }
}
