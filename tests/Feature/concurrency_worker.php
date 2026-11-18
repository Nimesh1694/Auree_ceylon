<?php

/**
 * Worker script for multi-process concurrency testing against file-backed SQLite in WAL mode.
 * Invocation: php concurrency_worker.php <worker_id> <mode> <args...>
 */

$repoPath = dirname(__DIR__, 2);
require $repoPath . '/vendor/autoload.php';

$app = require_once $repoPath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\RecentlyViewedMergeRecord;
use App\Models\RecentlyViewedProduct;
use App\Models\User;
use App\Services\RecentlyViewedProductService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

// Force connection to file-backed database with WAL and 30s busy timeout
$sqliteFile = $repoPath . '/database/test_conc.sqlite';
Config::set('database.default', 'sqlite');
Config::set('database.connections.sqlite.database', $sqliteFile);
Config::set('database.connections.sqlite.busy_timeout', 30000);
Config::set('database.connections.sqlite.journal_mode', 'WAL');
Config::set('database.connections.sqlite.transaction_mode', 'IMMEDIATE');
Config::set('database.connections.sqlite.options', [
    PDO::ATTR_TIMEOUT => 30,
]);

DB::purge('sqlite');
DB::reconnect('sqlite');

$pdo = DB::connection('sqlite')->getPdo();
$pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);
$pdo->exec('PRAGMA journal_mode = WAL;');
$pdo->exec('PRAGMA busy_timeout = 30000;');

$workerId = $argv[1] ?? 'unknown';
$mode     = $argv[2] ?? 'none';

$service = app(RecentlyViewedProductService::class);

try {
    switch ($mode) {
        case 'customer_a_views':
            $customerA = User::where('email', 'alice@example.com')->firstOrFail();
            // Record views for products 1 to 25 to force trimming to 20
            for ($pid = 1; $pid <= 25; $pid++) {
                $now = Carbon::parse('2026-11-14 10:00:00')->addMinutes($pid);
                Carbon::setTestNow($now);
                $service->recordView($customerA, $pid);
                usleep(10000); // 10ms jitter
            }
            echo "[WORKER-{$workerId}] SUCCESS: customer_a_views complete" . PHP_EOL;
            exit(0);

        case 'customer_a_concurrent_updates':
            $customerA = User::where('email', 'alice@example.com')->firstOrFail();
            // Concurrently write to products 10 to 30
            for ($pid = 10; $pid <= 30; $pid++) {
                // For pid 10, write a newer time first
                $newer = Carbon::parse('2026-11-14 16:00:00');
                Carbon::setTestNow($newer);
                $service->recordView($customerA, $pid);

                // Attempt to write an older time for pid 10
                $older = Carbon::parse('2026-11-14 11:00:00');
                Carbon::setTestNow($older);
                $service->recordView($customerA, $pid);

                usleep(15000); // 15ms jitter
            }
            echo "[WORKER-{$workerId}] SUCCESS: customer_a_concurrent_updates complete" . PHP_EOL;
            exit(0);

        case 'customer_b_views':
            $customerB = User::where('email', 'bob@example.com')->firstOrFail();
            // Record views for products 1 to 10 for Customer B
            for ($pid = 1; $pid <= 10; $pid++) {
                $now = Carbon::parse('2026-11-14 12:00:00')->addMinutes($pid);
                Carbon::setTestNow($now);
                $service->recordView($customerB, $pid);
                usleep(12000); // 12ms jitter
            }
            echo "[WORKER-{$workerId}] SUCCESS: customer_b_views complete" . PHP_EOL;
            exit(0);

        case 'customer_a_duplicate_merge':
            $customerA = User::where('email', 'alice@example.com')->firstOrFail();
            $sharedUuid = 'conc-idempotency-key-001';
            $payload = [
                ['product_id' => 1, 'viewed_at' => '2026-11-14T08:00:00Z'],
                ['product_id' => 2, 'viewed_at' => '2026-11-14T08:05:00Z'],
            ];

            // Run merge twice in rapid succession in this worker
            $res1 = $service->mergeGuestHistory($customerA, $sharedUuid, $payload);
            $res2 = $service->mergeGuestHistory($customerA, $sharedUuid, $payload);

            echo "[WORKER-{$workerId}] SUCCESS: merge1(replayed=" . ($res1['replayed'] ? 'true' : 'false') . ") merge2(replayed=" . ($res2['replayed'] ? 'true' : 'false') . ")" . PHP_EOL;
            exit(0);

        default:
            echo "[WORKER-{$workerId}] UNKNOWN_MODE: {$mode}" . PHP_EOL;
            exit(1);
    }
} catch (Throwable $e) {
    echo "[WORKER-{$workerId}] ERROR: " . get_class($e) . " - " . $e->getMessage() . PHP_EOL;
    exit(2);
}
