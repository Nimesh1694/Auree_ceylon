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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Class RecentlyViewedConcurrencyTest
 * 
 * Verifies SQLite atomic monotonic upsert behavior and genuine multi-process
 * concurrency against a file-backed SQLite database in WAL mode.
 */
class RecentlyViewedConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected RecentlyViewedProductService $service;
    protected User $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->service = app(RecentlyViewedProductService::class);

        $category = Category::create([
            'name_en'   => 'Body Care',
            'name_si'   => 'ශරීර රැකවරණය',
            'slug'      => 'body-care',
            'is_active' => true,
        ]);

        $this->customer = User::create([
            'name'     => 'Concurrent Customer',
            'email'    => 'concurrent@example.com',
            'password' => bcrypt('Password123!'),
            'role'     => UserRole::CUSTOMER,
            'status'   => UserStatus::ACTIVE,
        ]);

        $this->product = Product::create([
            'category_id'         => $category->id,
            'name_en'             => 'Soothing Lotion',
            'name_si'             => 'Soothing Lotion SI',
            'slug'                => 'soothing-lotion-' . Str::random(5),
            'sku'                 => 'SKU-CONC-' . strtoupper(Str::random(4)),
            'price'               => 1800.00,
            'stock_quantity'      => 15,
            'low_stock_threshold' => 3,
            'is_active'           => true,
        ]);
    }

    public function test_sqlite_atomic_monotonic_upsert_prevents_timestamp_regression(): void
    {
        $newerTime = '2026-11-14 15:00:00';
        $olderTime = '2026-11-14 14:00:00';

        // Write newer time first
        Carbon::setTestNow(Carbon::parse($newerTime));
        $this->service->recordView($this->customer, $this->product->id);

        $record1 = RecentlyViewedProduct::where('customer_id', $this->customer->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertSame($newerTime, $record1->viewed_at->format('Y-m-d H:i:s'));

        // Attempt write with older time
        Carbon::setTestNow(Carbon::parse($olderTime));
        $this->service->recordView($this->customer, $this->product->id);

        $record2 = RecentlyViewedProduct::where('customer_id', $this->customer->id)
            ->where('product_id', $this->product->id)
            ->first();

        // Monotonic invariant: must still be 15:00:00
        $this->assertSame($newerTime, $record2->viewed_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow(null);
    }

    public function test_sequential_rapid_views_for_same_customer_preserves_single_row_with_latest_timestamp(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Carbon::setTestNow(Carbon::parse('2026-11-14 08:00:00')->addMinutes($i));
            $this->service->recordView($this->customer, $this->product->id);
        }

        $records = RecentlyViewedProduct::where('customer_id', $this->customer->id)
            ->where('product_id', $this->product->id)
            ->get();

        // Exactly one row must exist
        $this->assertCount(1, $records);
        $this->assertSame('2026-11-14 08:10:00', $records->first()->viewed_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow(null);
    }

    /**
     * Genuine multi-process concurrency verification on file-backed SQLite in WAL mode.
     * Spawns 4 concurrent background processes:
     * - Worker 1: Customer A rapid views (triggering 20-record limit trimming).
     * - Worker 2: Customer A concurrent overlapping updates (testing monotonic race).
     * - Worker 3: Customer B concurrent views (verifying customer isolation).
     * - Worker 4: Customer A duplicate merge requests with same idempotency key (verifying serialization).
     */
    public function test_genuine_multiprocess_sqlite_wal_concurrency(): void
    {
        $sqliteFile = database_path('test_conc.sqlite');
        if (file_exists($sqliteFile)) {
            @unlink($sqliteFile);
        }
        @unlink($sqliteFile . '-wal');
        @unlink($sqliteFile . '-shm');
        touch($sqliteFile);

        // Configure temporary sqlite_conc connection
        Config::set('database.connections.sqlite_conc', [
            'driver'                  => 'sqlite',
            'database'                => $sqliteFile,
            'prefix'                  => '',
            'foreign_key_constraints' => true,
            'busy_timeout'            => 30000,
            'journal_mode'            => 'WAL',
            'transaction_mode'        => 'IMMEDIATE',
            'options'                 => [
                \PDO::ATTR_TIMEOUT => 30,
            ],
        ]);

        $conn = DB::connection('sqlite_conc');
        $pdo = $conn->getPdo();
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 30);
        $conn->statement('PRAGMA journal_mode = WAL;');
        $conn->statement('PRAGMA busy_timeout = 30000;');

        // Run migrations on file-backed database
        Artisan::call('migrate', [
            '--database' => 'sqlite_conc',
            '--force'    => true,
        ]);

        // Seed roles & permissions
        Artisan::call('db:seed', [
            '--class'    => 'Database\\Seeders\\RolesAndPermissionsSeeder',
            '--database' => 'sqlite_conc',
            '--force'    => true,
        ]);

        // Seed test data on file-backed DB
        $catId = $conn->table('categories')->insertGetId([
            'name_en'    => 'Conc Skin Care',
            'name_si'    => 'Conc Skin Care SI',
            'slug'       => 'conc-skin-care-' . Str::random(5),
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $custAId = $conn->table('users')->insertGetId([
            'name'       => 'Alice Conc',
            'email'      => 'alice@example.com',
            'password'   => bcrypt('Password123!'),
            'role'       => UserRole::CUSTOMER->value,
            'status'     => UserStatus::ACTIVE->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $custBId = $conn->table('users')->insertGetId([
            'name'       => 'Bob Conc',
            'email'      => 'bob@example.com',
            'password'   => bcrypt('Password123!'),
            'role'       => UserRole::CUSTOMER->value,
            'status'     => UserStatus::ACTIVE->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 1; $i <= 30; $i++) {
            $conn->table('products')->insert([
                'id'                  => $i,
                'category_id'         => $catId,
                'name_en'             => "Product Conc {$i}",
                'name_si'             => "Product Conc {$i} SI",
                'slug'                => "prod-conc-{$i}-" . Str::random(4),
                'sku'                 => "SKU-C-{$i}",
                'price'               => 1500.00,
                'stock_quantity'      => 20,
                'low_stock_threshold' => 2,
                'is_active'           => 1,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        // Release parent connection handle before spawning concurrent sub-processes
        $conn->disconnect();

        // Spawn 4 concurrent worker processes
        $phpBinary = 'C:\\xampp\\php\\php.exe';
        $workerScript = base_path('tests/Feature/concurrency_worker.php');

        $commands = [
            1 => [$phpBinary, $workerScript, '1', 'customer_a_views'],
            2 => [$phpBinary, $workerScript, '2', 'customer_a_concurrent_updates'],
            3 => [$phpBinary, $workerScript, '3', 'customer_b_views'],
            4 => [$phpBinary, $workerScript, '4', 'customer_a_duplicate_merge'],
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $processes = [];
        $pipes     = [];

        // Launch all simultaneously
        foreach ($commands as $id => $cmd) {
            $cmdLine = implode(' ', array_map('escapeshellarg', $cmd));
            $proc = proc_open($cmdLine, $descriptors, $pPipes, base_path());
            $this->assertIsResource($proc, "Failed to spawn worker {$id}");
            $processes[$id] = $proc;
            $pipes[$id]     = $pPipes;
        }

        // Wait for completion and gather results
        $outputs   = [];
        $exitCodes = [];
        $startTime = time();
        $timeout   = 45; // 45 seconds maximum

        while (count($processes) > 0) {
            if ((time() - $startTime) > $timeout) {
                foreach ($processes as $id => $p) {
                    proc_terminate($p);
                }
                $this->fail("Multi-process concurrency test timed out after {$timeout} seconds.");
            }

            foreach ($processes as $id => $proc) {
                $status = proc_get_status($proc);
                if (!$status['running']) {
                    $out = stream_get_contents($pipes[$id][1]);
                    $err = stream_get_contents($pipes[$id][2]);
                    fclose($pipes[$id][0]);
                    fclose($pipes[$id][1]);
                    fclose($pipes[$id][2]);
                    proc_close($proc);

                    $outputs[$id]   = trim($out . ' ' . $err);
                    $exitCodes[$id] = $status['exitcode'];
                    unset($processes[$id]);
                }
            }
            usleep(20000); // 20ms polling
        }

        // 1. Assert all 4 workers completed successfully with exit code 0
        foreach ($exitCodes as $id => $code) {
            $this->assertSame(0, $code, "Worker {$id} failed with exit code {$code}. Output: " . ($outputs[$id] ?? 'none'));
        }

        // Reconnect parent to inspect database state
        $conn = DB::connection('sqlite_conc');

        // 2. Customer A 20-record limit invariant
        $custACount = $conn->table('recently_viewed_products')
            ->where('customer_id', $custAId)
            ->count();
        $this->assertLessThanOrEqual(20, $custACount, "Customer A exceeded 20-record limit under concurrency.");

        // 3. Customer B cross-customer isolation invariant: Customer B must have exactly 10 records
        $custBCount = $conn->table('recently_viewed_products')
            ->where('customer_id', $custBId)
            ->count();
        $this->assertSame(10, $custBCount, "Customer B record count compromised by concurrent Customer A writes.");

        // 4. Monotonic invariant for Product 10 (Worker 2 attempted older timestamp after newer)
        $prod10 = $conn->table('recently_viewed_products')
            ->where('customer_id', $custAId)
            ->where('product_id', 10)
            ->first();
        if ($prod10 !== null) {
            $this->assertSame('2026-11-14 16:00:00', $prod10->viewed_at, "Monotonic timestamp regressed under concurrent writes.");
        }

        // 5. Duplicate merge record idempotency invariant
        $mergeCount = $conn->table('recently_viewed_merge_records')
            ->where('customer_id', $custAId)
            ->where('idempotency_key', 'conc-idempotency-key-001')
            ->count();
        $this->assertSame(1, $mergeCount, "Duplicate idempotency merge records created under concurrency.");

        // Clean up test DB
        $conn->disconnect();
        @unlink($sqliteFile);
        @unlink($sqliteFile . '-wal');
        @unlink($sqliteFile . '-shm');
    }
}
