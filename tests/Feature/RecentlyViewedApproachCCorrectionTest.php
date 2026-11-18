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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Class RecentlyViewedApproachCCorrectionTest
 * 
 * Targeted verification suite for Approach C corrective implementation:
 * - Deterministic ASC product-ID ordering
 * - Bounded retry strategy (max 3 attempts, Error 1213 / 1205 differentiation)
 * - Controlled HttpException(429) on exhausted concurrency retries
 * - Optimized trimToLimit query behavior
 * - Same-customer and cross-customer isolation
 */
class RecentlyViewedApproachCCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected RecentlyViewedProductService $service;
    protected User $customer1;
    protected User $customer2;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->service = app(RecentlyViewedProductService::class);

        $this->category = Category::create([
            'name_en'   => 'Skincare',
            'name_si'   => 'සම රැකවරණය',
            'slug'      => 'skincare',
            'is_active' => true,
        ]);

        $this->customer1 = User::create([
            'name'     => 'Customer One',
            'email'    => 'cust1@test.auraluxe.local',
            'password' => bcrypt('Secret123!'),
            'role'     => UserRole::CUSTOMER,
            'status'   => UserStatus::ACTIVE,
        ]);

        $this->customer2 = User::create([
            'name'     => 'Customer Two',
            'email'    => 'cust2@test.auraluxe.local',
            'password' => bcrypt('Secret123!'),
            'role'     => UserRole::CUSTOMER,
            'status'   => UserStatus::ACTIVE,
        ]);
    }

    protected function createProduct(int $id, string $name, bool $isActive = true): Product
    {
        return Product::unguarded(function () use ($id, $name, $isActive) {
            return Product::create([
                'id'                  => $id,
                'category_id'         => $this->category->id,
                'name_en'             => $name,
                'name_si'             => $name . ' SI',
                'slug'                => Str::slug($name) . '-' . $id,
                'sku'                 => 'SKU-' . $id,
                'price'               => 1500.00,
                'stock_quantity'      => 50,
                'low_stock_threshold' => 5,
                'is_active'           => $isActive,
            ]);
        });
    }

    /** 1. Focused Merge: Successful merge of active products */
    public function test_focused_merge_inserts_active_products_and_creates_idempotency_record(): void
    {
        $p1 = $this->createProduct(101, 'Serum A');
        $p2 = $this->createProduct(102, 'Serum B');

        $key = (string) Str::uuid();
        $now = Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');
        $payload = [
            ['product_id' => $p1->id, 'viewed_at' => $now],
            ['product_id' => $p2->id, 'viewed_at' => $now],
        ];

        $res = $this->service->mergeGuestHistory($this->customer1, $key, $payload);

        $this->assertTrue($res['success']);
        $this->assertSame(2, $res['merged_count']);
        $this->assertFalse($res['replayed']);

        $this->assertDatabaseHas('recently_viewed_merge_records', [
            'customer_id'     => $this->customer1->id,
            'idempotency_key' => $key,
            'merged_count'    => 2,
        ]);

        $this->assertDatabaseHas('recently_viewed_products', [
            'customer_id' => $this->customer1->id,
            'product_id'  => $p1->id,
        ]);
        $this->assertDatabaseHas('recently_viewed_products', [
            'customer_id' => $this->customer1->id,
            'product_id'  => $p2->id,
        ]);
    }

    /** 2. Idempotency: Replaying with identical key returns replayed = true */
    public function test_idempotency_replay_returns_cached_merged_count(): void
    {
        $p = $this->createProduct(103, 'Toner');
        $key = (string) Str::uuid();
        $payload = [['product_id' => $p->id, 'viewed_at' => '2026-11-14T08:00:00Z']];

        $res1 = $this->service->mergeGuestHistory($this->customer1, $key, $payload);
        $res2 = $this->service->mergeGuestHistory($this->customer1, $key, $payload);

        $this->assertTrue($res1['success']);
        $this->assertFalse($res1['replayed']);

        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['replayed']);
        $this->assertSame($res1['merged_count'], $res2['merged_count']);

        // Exactly one merge record exists
        $this->assertSame(1, RecentlyViewedMergeRecord::where('idempotency_key', $key)->count());
    }

    /** 3. Idempotency Mismatch: Reusing key with different payload throws ConflictHttpException (409) */
    public function test_idempotency_key_reuse_with_differing_payload_throws_409(): void
    {
        $p1 = $this->createProduct(104, 'Cream A');
        $p2 = $this->createProduct(105, 'Cream B');
        $key = (string) Str::uuid();

        $this->service->mergeGuestHistory($this->customer1, $key, [
            ['product_id' => $p1->id, 'viewed_at' => '2026-11-14T08:00:00Z']
        ]);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Idempotency key reused with a different payload.');

        $this->service->mergeGuestHistory($this->customer1, $key, [
            ['product_id' => $p2->id, 'viewed_at' => '2026-11-14T08:00:00Z']
        ]);
    }

    /** 4. Cross-Customer Isolation: Same idempotency key operates independently for two customers */
    public function test_same_idempotency_key_operates_independently_across_customers(): void
    {
        $p = $this->createProduct(106, 'Lotion');
        $sharedKey = (string) Str::uuid();
        $payload = [['product_id' => $p->id, 'viewed_at' => '2026-11-14T08:00:00Z']];

        $res1 = $this->service->mergeGuestHistory($this->customer1, $sharedKey, $payload);
        $res2 = $this->service->mergeGuestHistory($this->customer2, $sharedKey, $payload);

        $this->assertTrue($res1['success']);
        $this->assertFalse($res1['replayed']);
        $this->assertTrue($res2['success']);
        $this->assertFalse($res2['replayed']);

        $this->assertSame(2, RecentlyViewedMergeRecord::where('idempotency_key', $sharedKey)->count());
    }

    /** 5. Bounded Retry: Concurrency retry exhaustion throws HttpException(429) */
    public function test_concurrency_exhaustion_throws_http_429(): void
    {
        $p = $this->createProduct(107, 'Mask');
        $key = (string) Str::uuid();
        $payload = [['product_id' => $p->id, 'viewed_at' => '2026-11-14T08:00:00Z']];

        // Mock DB::transaction to simulate persistent MySQL Deadlock 1213
        $attempts = 0;
        DB::shouldReceive('transaction')
            ->times(3)
            ->andReturnUsing(function () use (&$attempts) {
                $attempts++;
                $pdoEx = new \PDOException('Deadlock found when trying to get lock', 1213);
                $pdoEx->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
                throw new QueryException(
                    'mysql',
                    'INSERT INTO recently_viewed_products ...',
                    [],
                    $pdoEx
                );
            });

        // Other facade methods required during bootstrap
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        try {
            $this->service->mergeGuestHistory($this->customer1, $key, $payload);
            $this->fail('Expected HttpException 429 was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertStringContainsString('high concurrency', $e->getMessage());
            $this->assertSame(3, $attempts, 'Expected exactly 3 retry attempts before exhaustion.');
        }
    }

    /** 6. Non-concurrency QueryException is NOT retried and bubbles up immediately */
    public function test_non_concurrency_query_exception_is_not_retried(): void
    {
        $p = $this->createProduct(108, 'Cleanser');
        $key = (string) Str::uuid();
        $payload = [['product_id' => $p->id, 'viewed_at' => '2026-11-14T08:00:00Z']];

        $attempts = 0;
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function () use (&$attempts) {
                $attempts++;
                throw new QueryException(
                    'mysql',
                    'SYNTAX ERROR IN QUERY ...',
                    [],
                    new \Exception('Table not found', 1146)
                );
            });

        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        try {
            $this->service->mergeGuestHistory($this->customer1, $key, $payload);
            $this->fail('Expected QueryException was not thrown.');
        } catch (QueryException $e) {
            $this->assertSame(1, $attempts, 'Non-concurrency error must not be retried.');
        }
    }

    /** 7. Optimized trimToLimit: Preserves exactly 20 items, pruning surplus by viewed_at DESC, id DESC */
    public function test_optimized_trim_to_limit_preserves_20_newest_items(): void
    {
        // Insert 25 products
        $products = [];
        for ($i = 1; $i <= 25; $i++) {
            $products[$i] = $this->createProduct($i, "Product {$i}");
            Carbon::setTestNow(Carbon::parse('2026-11-14 00:00:00')->addMinutes($i));
            $this->service->recordView($this->customer1, $products[$i]->id);
        }
        Carbon::setTestNow(null);

        $remaining = RecentlyViewedProduct::where('customer_id', $this->customer1->id)->count();
        $this->assertSame(20, $remaining, 'Trim must maintain exactly 20 records.');

        // Products 1 through 5 (oldest) must have been trimmed
        for ($i = 1; $i <= 5; $i++) {
            $this->assertDatabaseMissing('recently_viewed_products', [
                'customer_id' => $this->customer1->id,
                'product_id'  => $products[$i]->id,
            ]);
        }

        // Products 6 through 25 (newest 20) must exist
        for ($i = 6; $i <= 25; $i++) {
            $this->assertDatabaseHas('recently_viewed_products', [
                'customer_id' => $this->customer1->id,
                'product_id'  => $products[$i]->id,
            ]);
        }
    }

    /** 8. Monotonic viewed_at: Newer timestamp updates, older timestamp does not regress */
    public function test_monotonic_timestamp_preservation(): void
    {
        $p = $this->createProduct(301, 'Monotonic Serum');
        $time1 = '2026-11-14 12:00:00';
        $timeOlder = '2026-11-14 10:00:00';
        $timeNewer = '2026-11-14 14:00:00';

        Carbon::setTestNow(Carbon::parse($time1));
        $this->service->recordView($this->customer1, $p->id);

        $row1 = RecentlyViewedProduct::where('customer_id', $this->customer1->id)->where('product_id', $p->id)->first();
        $this->assertSame($time1, $row1->viewed_at->format('Y-m-d H:i:s'));

        // Older time attempt
        Carbon::setTestNow(Carbon::parse($timeOlder));
        $this->service->recordView($this->customer1, $p->id);

        $row2 = RecentlyViewedProduct::where('customer_id', $this->customer1->id)->where('product_id', $p->id)->first();
        $this->assertSame($time1, $row2->viewed_at->format('Y-m-d H:i:s'), 'Older time must not regress timestamp.');

        // Newer time attempt
        Carbon::setTestNow(Carbon::parse($timeNewer));
        $this->service->recordView($this->customer1, $p->id);

        $row3 = RecentlyViewedProduct::where('customer_id', $this->customer1->id)->where('product_id', $p->id)->first();
        $this->assertSame($timeNewer, $row3->viewed_at->format('Y-m-d H:i:s'), 'Newer time must advance timestamp.');

        Carbon::setTestNow(null);
    }

    /** 9. D-01: MySQL 1205 Lock Wait Timeout fails fast without retrying */
    public function test_lock_wait_timeout_1205_fails_fast_without_retrying(): void
    {
        $p = $this->createProduct(401, 'Timeout Test Serum');
        $key = (string) Str::uuid();
        $payload = [['product_id' => $p->id, 'viewed_at' => '2026-11-14T08:00:00Z']];

        $attempts = 0;
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function () use (&$attempts) {
                $attempts++;
                $pdoEx = new \PDOException('Lock wait timeout exceeded; try restarting transaction', 1205);
                $pdoEx->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'];
                throw new QueryException(
                    'mysql',
                    'INSERT INTO recently_viewed_products ...',
                    [],
                    $pdoEx
                );
            });

        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        try {
            $this->service->mergeGuestHistory($this->customer1, $key, $payload);
            $this->fail('Expected HttpException 429 was not thrown on lock wait timeout.');
        } catch (HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertStringContainsString('timed out due to high lock contention', $e->getMessage());
            $this->assertSame(1, $attempts, 'Error 1205 must fail fast without retry attempts.');
        }
    }

    /** 10. D-02: Comprehensive retention invariant verification for 20, 21, 70, 71, 100, 500 records */
    public function test_trim_to_limit_retention_invariant_across_all_edge_case_counts(): void
    {
        $refMethod = new \ReflectionMethod(RecentlyViewedProductService::class, 'trimToLimit');
        $refMethod->setAccessible(true);

        // Pre-seed 500 products in bulk for this test
        $bulkProducts = [];
        $now = now()->toDateTimeString();
        for ($p = 1; $p <= 500; $p++) {
            $bulkProducts[] = [
                'id'                  => 1000 + $p,
                'category_id'         => $this->category->id,
                'name_en'             => "Bulk Product {$p}",
                'name_si'             => "Bulk Product {$p} SI",
                'slug'                => "bulk-product-{$p}-" . (1000 + $p),
                'sku'                 => "SKU-BULK-{$p}",
                'price'               => 1500.00,
                'stock_quantity'      => 50,
                'low_stock_threshold' => 5,
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }
        foreach (array_chunk($bulkProducts, 100) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        $countsToTest = [20, 21, 70, 71, 100, 500];

        foreach ($countsToTest as $idx => $totalCount) {
            $testCustomer = User::create([
                'name'     => "Retention Customer {$totalCount}",
                'email'    => "retention_{$totalCount}_{$idx}@test.auraluxe.local",
                'password' => bcrypt('Secret123!'),
                'role'     => UserRole::CUSTOMER,
                'status'   => UserStatus::ACTIVE,
            ]);

            // Seed bulk records directly
            $rows = [];
            $baseTime = Carbon::parse('2026-11-01 00:00:00');
            for ($i = 1; $i <= $totalCount; $i++) {
                $rows[] = [
                    'customer_id' => $testCustomer->id,
                    'product_id'  => 1000 + $i,
                    'viewed_at'   => $baseTime->copy()->addMinutes($i)->format('Y-m-d H:i:s'),
                ];
            }

            // Chunk insert to avoid SQLite/MySQL query size limits
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('recently_viewed_products')->insert($chunk);
            }

            $beforeCount = RecentlyViewedProduct::where('customer_id', $testCustomer->id)->count();
            $this->assertSame($totalCount, $beforeCount);

            // Execute trimToLimit
            $refMethod->invoke($this->service, $testCustomer->id, 20);

            $afterCount = RecentlyViewedProduct::where('customer_id', $testCustomer->id)->count();
            $expectedCount = min(20, $totalCount);
            $this->assertSame($expectedCount, $afterCount, "Failed for initial count {$totalCount}: expected {$expectedCount}, got {$afterCount}");
        }
    }

    /** 11. D-02: Trim preserves tie-breaking by id DESC when viewed_at is identical */
    public function test_trim_to_limit_tie_breaking_by_id_desc(): void
    {
        $refMethod = new \ReflectionMethod(RecentlyViewedProductService::class, 'trimToLimit');
        $refMethod->setAccessible(true);

        // Pre-seed 25 products for tie-breaking test
        $tieProducts = [];
        $now = now()->toDateTimeString();
        for ($p = 1; $p <= 25; $p++) {
            $tieProducts[] = [
                'id'                  => 2000 + $p,
                'category_id'         => $this->category->id,
                'name_en'             => "Tie Product {$p}",
                'name_si'             => "Tie Product {$p} SI",
                'slug'                => "tie-product-{$p}-" . (2000 + $p),
                'sku'                 => "SKU-TIE-{$p}",
                'price'               => 1500.00,
                'stock_quantity'      => 50,
                'low_stock_threshold' => 5,
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }
        DB::table('products')->insert($tieProducts);

        $testCustomer = User::create([
            'name'     => 'Tie Break Customer',
            'email'    => 'tiebreak@test.auraluxe.local',
            'password' => bcrypt('Secret123!'),
            'role'     => UserRole::CUSTOMER,
            'status'   => UserStatus::ACTIVE,
        ]);

        $sameTimestamp = '2026-11-14 12:00:00';
        $rows = [];
        for ($i = 1; $i <= 25; $i++) {
            $rows[] = [
                'customer_id' => $testCustomer->id,
                'product_id'  => 2000 + $i,
                'viewed_at'   => $sameTimestamp,
            ];
        }

        DB::table('recently_viewed_products')->insert($rows);

        $refMethod->invoke($this->service, $testCustomer->id, 20);

        $remainingIds = RecentlyViewedProduct::where('customer_id', $testCustomer->id)
            ->pluck('product_id')
            ->all();

        $this->assertCount(20, $remainingIds);

        // When viewed_at is identical, the newest 20 by id DESC should be preserved (2006 to 2025)
        for ($i = 1; $i <= 5; $i++) {
            $this->assertNotContains(2000 + $i, $remainingIds, "Product id " . (2000 + $i) . " should have been trimmed");
        }
        for ($i = 6; $i <= 25; $i++) {
            $this->assertContains(2000 + $i, $remainingIds, "Product id " . (2000 + $i) . " should have been kept");
        }
    }

    /** 12. D-01: MySQL session timeout is captured and strictly restored on completion */
    public function test_d01_mysql_session_timeout_captured_and_restored_in_finally(): void
    {
        $p = $this->createProduct(501, 'Restoration Serum');
        $key = (string) Str::uuid();
        $payload = [['product_id' => $p->id, 'viewed_at' => '2026-11-15T08:00:00Z']];

        $statements = [];
        DB::shouldReceive('getDriverName')->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->with('SELECT @@SESSION.innodb_lock_wait_timeout AS timeout_val')
            ->once()
            ->andReturn((object) ['timeout_val' => 50]);

        DB::shouldReceive('statement')
            ->with('SET SESSION innodb_lock_wait_timeout = 2')
            ->once()
            ->andReturnUsing(function ($sql) use (&$statements) {
                $statements[] = $sql;
                return true;
            });

        DB::shouldReceive('statement')
            ->with('SET SESSION innodb_lock_wait_timeout = 50')
            ->once()
            ->andReturnUsing(function ($sql) use (&$statements) {
                $statements[] = $sql;
                return true;
            });

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) {
                return [
                    'success'      => true,
                    'merged_count' => 1,
                    'replayed'     => false,
                ];
            });

        $res = $this->service->mergeGuestHistory($this->customer1, $key, $payload);
        $this->assertTrue($res['success']);
        $this->assertContains('SET SESSION innodb_lock_wait_timeout = 2', $statements);
        $this->assertContains('SET SESSION innodb_lock_wait_timeout = 50', $statements);
    }

    /** 13. D-01: Restoration failure disconnects database safely and preserves primary exception */
    public function test_d01_restoration_failure_disconnects_safely_and_preserves_primary_exception(): void
    {
        $p = $this->createProduct(502, 'Restoration Failure Serum');
        $key = (string) Str::uuid();
        $payload = [['product_id' => $p->id, 'viewed_at' => '2026-11-15T08:00:00Z']];

        $disconnected = false;
        DB::shouldReceive('getDriverName')->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->with('SELECT @@SESSION.innodb_lock_wait_timeout AS timeout_val')
            ->once()
            ->andReturn((object) ['timeout_val' => 50]);

        DB::shouldReceive('statement')
            ->with('SET SESSION innodb_lock_wait_timeout = 2')
            ->once()
            ->andReturn(true);

        // Transaction throws 1205 lock wait timeout
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function () {
                $pdoEx = new \PDOException('Lock wait timeout exceeded; try restarting transaction', 1205);
                $pdoEx->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'];
                throw new QueryException('mysql', 'INSERT ...', [], $pdoEx);
            });

        // Finally tries to restore but statement throws (e.g. connection broken)
        DB::shouldReceive('statement')
            ->with('SET SESSION innodb_lock_wait_timeout = 50')
            ->once()
            ->andThrow(new \PDOException('MySQL server has gone away', 2006));

        DB::shouldReceive('disconnect')
            ->once()
            ->andReturnUsing(function () use (&$disconnected) {
                $disconnected = true;
            });

        try {
            $this->service->mergeGuestHistory($this->customer1, $key, $payload);
            $this->fail('Expected HttpException 429 was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertStringContainsString('timed out due to high lock contention', $e->getMessage());
            $this->assertTrue($disconnected, 'Connection must be disconnected when restoration fails.');
        }
    }
}
