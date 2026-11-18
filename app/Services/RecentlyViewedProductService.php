<?php

namespace App\Services;

use App\Models\Product;
use App\Models\RecentlyViewedMergeRecord;
use App\Models\RecentlyViewedProduct;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class RecentlyViewedProductService
{
    /**
     * Compute a deterministic SHA-256 hash from raw client payload items.
     * Normalized: integer product_id, UTC Zulu format, deduplicated by recency, sorted ascending.
     */
    public function computeCanonicalHash(array $items): string
    {
        $deduped = [];
        foreach ($items as $item) {
            $pid     = (int) $item['product_id'];
            $utcZulu = Carbon::parse($item['viewed_at'])->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
            if (!isset($deduped[$pid]) || $utcZulu > $deduped[$pid]) {
                $deduped[$pid] = $utcZulu;
            }
        }
        ksort($deduped, SORT_NUMERIC);

        $canonicalList = [];
        foreach ($deduped as $pid => $utcZulu) {
            $canonicalList[] = ['product_id' => $pid, 'viewed_at' => $utcZulu];
        }

        return hash('sha256', json_encode($canonicalList, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Record a single product view for an authenticated customer.
     * Fail-open: exceptions are caught and safely logged. Page render is never interrupted.
     */
    public function recordView(User $customer, int $productId): void
    {
        $maxAttempts = 6;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                DB::transaction(function () use ($customer, $productId) {
                    // Lock customer row to serialize customer-scoped writes (MySQL InnoDB row locking)
                    if (DB::getDriverName() !== 'sqlite') {
                        User::where('id', $customer->id)->lockForUpdate()->first();
                    }

                    $isActive = Product::where('id', $productId)->where('is_active', true)->exists();
                    if (!$isActive) {
                        return;
                    }

                    $now = Carbon::now('UTC')->format('Y-m-d H:i:s');
                    $this->upsertMonotonic($customer->id, $productId, $now);
                    $this->trimToLimit($customer->id);
                }, 1);
                return;
            } catch (Throwable $e) {
                if ($attempt >= $maxAttempts) {
                    // CR-34: Safe logging — no raw SQL, no message dumps, no bindings, no payload leaks
                    Log::channel('daily')->warning('Recently viewed recording failed', [
                        'diagnostic_code' => 'ERR_RVP_RECORD_VIEW_FAILED',
                        'error_class'     => get_class($e),
                        'error_code'      => $e->getCode(),
                    ]);
                    return;
                }
                usleep(random_int(15000, 45000));
            }
        }
    }

    /**
     * Retrieve customer recently viewed items with hard cap of 20 enforced in query.
     */
    public function getForCustomer(
        User $customer,
        int  $limit          = 20,
        ?int $excludeProductId = null
    ): Collection {
        $safeLimit = max(1, min($limit, 20));

        return RecentlyViewedProduct::query()
            ->where('customer_id', $customer->id)
            ->where('viewed_at', '>=', Carbon::now('UTC')->subDays(60)->format('Y-m-d H:i:s'))
            ->whereHas('product', fn($q) => $q->where('is_active', true))
            ->when($excludeProductId !== null, fn($q) => $q->where('product_id', '!=', $excludeProductId))
            ->with(['product' => fn($q) => $q->where('is_active', true)->with('primaryImage')])
            ->orderBy('viewed_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($safeLimit)
            ->get()
            ->filter(fn($r) => $r->product !== null)
            ->values();
    }

    /**
     * Performance instrumentation telemetry for the most recent mergeGuestHistory execution.
     * @var array<string, mixed>|null
     */
    public static ?array $lastTelemetry = null;

    /**
     * Merge guest history into authenticated customer account.
     */
    public function mergeGuestHistory(User $customer, string $idempotencyKey, array $rawItems): array
    {
        $serviceStartTime = hrtime(true);

        // Phase 1: Pre-Transaction Preparation (ZERO LOCKS HELD)
        $prepStartTime = hrtime(true);

        $payloadHash = $this->computeCanonicalHash($rawItems);

        $now    = Carbon::now('UTC');
        $cutoff = $now->copy()->subDays(60);

        // Deduplicate items with post-hash future clamping
        $deduped = [];
        foreach ($rawItems as $item) {
            $pid      = (int) $item['product_id'];
            $viewedAt = Carbon::parse($item['viewed_at'])->setTimezone('UTC');
            if ($viewedAt->gt($now->copy()->addSeconds(60))) {
                $viewedAt = $now->copy();
            }
            if (!isset($deduped[$pid]) || $viewedAt->gt($deduped[$pid])) {
                $deduped[$pid] = $viewedAt;
            }
        }

        // MVCC consistent read of active catalog (Non-blocking consistent snapshot read)
        $candidateIds     = array_keys($deduped);
        $activeProductIds = Product::whereIn('id', $candidateIds)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        $eligible = [];
        foreach ($deduped as $pid => $viewedAt) {
            if ($viewedAt->gte($cutoff) && in_array($pid, $activeProductIds, true)) {
                $eligible[] = ['product_id' => $pid, 'viewed_at' => $viewedAt];
            }
        }

        usort($eligible, function ($a, $b) {
            $cmp = $b['viewed_at']->timestamp <=> $a['viewed_at']->timestamp;
            return $cmp !== 0 ? $cmp : ($b['product_id'] <=> $a['product_id']);
        });

        $topItems    = array_slice($eligible, 0, 15);
        $mergedCount = count($topItems);

        $upsertItems = $topItems;
        if ($mergedCount > 0) {
            // Defensive deterministic ordering by product_id ASC for batch upsert
            // to enforce a uniform lock acquisition sequence on foreign-key referenced parent rows
            usort($upsertItems, fn($a, $b) => $a['product_id'] <=> $b['product_id']);
        }

        $prepDurationNs = hrtime(true) - $prepStartTime;

        $maxAttempts = 12;
        $totalTimeoutOverheadNs = 0;
        $lastLockWaitNs = 0;
        $lastLockHeldNs = 0;
        $lastTxDurationNs = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $primaryException = null;
            $originalTimeout  = null;
            $isMysql          = (DB::getDriverName() === 'mysql');

            // D-01-A: Capture exact original session timeout prior to changing it
            $tTimeoutStart = hrtime(true);
            if ($isMysql) {
                $row = DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS timeout_val');
                $originalTimeout = $row ? (int) $row->timeout_val : null;
            }

            try {
                // D-01-B: Apply merge-specific timeout (2 seconds)
                if ($isMysql) {
                    DB::statement('SET SESSION innodb_lock_wait_timeout = 2');
                }
                $totalTimeoutOverheadNs += (hrtime(true) - $tTimeoutStart);

                $txStart = hrtime(true);
                $result = DB::transaction(function () use (
                    $customer,
                    $idempotencyKey,
                    $payloadHash,
                    $upsertItems,
                    $mergedCount,
                    $now,
                    &$lastLockWaitNs,
                    &$lastLockHeldNs
                ) {
                    // Phase 2: Minimal Atomic Write Transaction (Customer Lock Acquired Strictly for Atomic Writes)
                    $lockAcquireStart = hrtime(true);
                    if (DB::getDriverName() !== 'sqlite') {
                        User::where('id', $customer->id)->lockForUpdate()->first();
                    }
                    $lastLockWaitNs = hrtime(true) - $lockAcquireStart;

                    $lockHeldStart = hrtime(true);

                    // 2. Idempotency lookup
                    $existing = RecentlyViewedMergeRecord::where('customer_id', $customer->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        if ($existing->payload_hash !== $payloadHash) {
                            $lastLockHeldNs = hrtime(true) - $lockHeldStart;
                            throw new ConflictHttpException('Idempotency key reused with a different payload.');
                        }
                        $lastLockHeldNs = hrtime(true) - $lockHeldStart;
                        return [
                            'success'      => true,
                            'merged_count' => (int) $existing->merged_count,
                            'replayed'     => true,
                        ];
                    }

                    // 4. Batch upsert recently viewed records (Pre-sorted deterministically)
                    if ($mergedCount > 0) {
                        $this->batchUpsertMonotonic($customer->id, $upsertItems);
                        // 5. Iterative retention pruning until customer has no more than 20 records
                        $this->trimToLimit($customer->id);
                    }

                    // 6. Insert merge record (Always write idempotency record even if mergedCount === 0)
                    RecentlyViewedMergeRecord::create([
                        'customer_id'     => $customer->id,
                        'idempotency_key' => $idempotencyKey,
                        'payload_hash'    => $payloadHash,
                        'merged_count'    => $mergedCount,
                        'created_at'      => $now->format('Y-m-d H:i:s'),
                    ]);

                    $lastLockHeldNs = hrtime(true) - $lockHeldStart;

                    return [
                        'success'      => true,
                        'merged_count' => $mergedCount,
                        'replayed'     => false,
                    ];
                }, 1);

                $lastTxDurationNs = hrtime(true) - $txStart;

                self::$lastTelemetry = [
                    'prep_duration_ms'          => round($prepDurationNs / 1e6, 3),
                    'lock_wait_duration_ms'     => round($lastLockWaitNs / 1e6, 3),
                    'lock_held_duration_ms'     => round($lastLockHeldNs / 1e6, 3),
                    'transaction_duration_ms'   => round($lastTxDurationNs / 1e6, 3),
                    'timeout_overhead_ms'       => round($totalTimeoutOverheadNs / 1e6, 3),
                    'total_service_duration_ms' => round((hrtime(true) - $serviceStartTime) / 1e6, 3),
                    'attempt'                   => $attempt,
                ];

                return $result;
            } catch (ConflictHttpException $e) {
                $primaryException = $e;
                throw $e;
            } catch (QueryException $e) {
                $primaryException = $e;
                $errorCode = (int) ($e->errorInfo[1] ?? $e->getCode());
                $sqlState  = (string) $e->getCode();
                $msg       = $e->getMessage();

                // Identify transient concurrency failures:
                // MySQL 1213: Deadlock found when trying to get lock
                // MySQL 1205: Lock wait timeout exceeded
                // SQLite 5: database is locked / busy
                $isDeadlock    = ($errorCode === 1213 || $sqlState === '40001' || str_contains($msg, '1213') || str_contains($msg, 'Deadlock'));
                $isLockTimeout = ($errorCode === 1205 || str_contains($msg, '1205') || str_contains($msg, 'Lock wait timeout'));
                $isSqliteBusy  = ($errorCode === 5 || str_contains($msg, 'database is locked'));

                // D-01: Lock wait timeout must NOT be retried. Fail fast directly to 429 to avoid cascading wait queues.
                if ($isLockTimeout) {
                    Log::channel('daily')->warning('Recently viewed merge lock wait timeout exceeded', [
                        'diagnostic_code' => 'ERR_RVP_MERGE_LOCK_TIMEOUT',
                        'error_code'      => $errorCode,
                        'sql_state'       => $sqlState,
                        'customer_id'     => $customer->id,
                        'attempt'         => $attempt,
                    ]);

                    throw new HttpException(
                        429,
                        'Merge operation timed out due to high lock contention. Please retry.'
                    );
                }

                // D-01: Deadlock (MySQL: strictly max 3 attempts) and SQLite busy (file-lock: up to 12 attempts)
                $canRetry = ($isDeadlock && $attempt < 3) || ($isSqliteBusy && $attempt < 12);
                if ($canRetry) {
                    // Controlled exponential backoff with jitter:
                    // MySQL Deadlock: Attempt 1: 20ms to 45ms, Attempt 2: 50ms to 90ms
                    $backoffUs = match ($attempt) {
                        1 => random_int(20000, 45000),
                        default => random_int(50000, 90000),
                    };
                    usleep($backoffUs);
                    continue;
                }

                if ($isDeadlock || $isSqliteBusy) {
                    Log::channel('daily')->warning('Recently viewed merge concurrency exhausted', [
                        'diagnostic_code' => 'ERR_RVP_MERGE_CONCURRENCY_EXHAUSTED',
                        'error_code'      => $errorCode,
                        'sql_state'       => $sqlState,
                        'customer_id'     => $customer->id,
                        'attempt'         => $attempt,
                    ]);

                    throw new HttpException(
                        429,
                        'Merge operation could not be completed due to high concurrency. Please retry.'
                    );
                }

                throw $e;
            } catch (Throwable $e) {
                $primaryException = $e;
                throw $e;
            } finally {
                // D-01-B: Safely restore the exact original session timeout
                if ($isMysql && $originalTimeout !== null) {
                    $tRestoreStart = hrtime(true);
                    try {
                        DB::statement('SET SESSION innodb_lock_wait_timeout = ' . (int) $originalTimeout);
                        $totalTimeoutOverheadNs += (hrtime(true) - $tRestoreStart);
                        if (self::$lastTelemetry !== null) {
                            self::$lastTelemetry['timeout_overhead_ms'] = round($totalTimeoutOverheadNs / 1e6, 3);
                            self::$lastTelemetry['total_service_duration_ms'] = round((hrtime(true) - $serviceStartTime) / 1e6, 3);
                        }
                    } catch (Throwable $restorationEx) {
                        Log::channel('daily')->critical('Failed to restore MySQL innodb_lock_wait_timeout; dropping connection', [
                            'diagnostic_code'   => 'ERR_RVP_TIMEOUT_RESTORATION_FAILED',
                            'original_timeout'  => $originalTimeout,
                            'restoration_error' => $restorationEx->getMessage(),
                            'primary_exception' => $primaryException ? $primaryException->getMessage() : null,
                        ]);

                        try {
                            DB::disconnect();
                        } catch (Throwable $ignored) {}

                        // Ensure restoration errors do not silently hide the original transaction exception
                        if (!$primaryException) {
                            throw new \RuntimeException('Failed to restore database session timeout: ' . $restorationEx->getMessage(), 0, $restorationEx);
                        }
                    }
                }
            }
        }

        throw new HttpException(429, 'Merge operation timed out after retries.');
    }

    public function pruneExpiredViewRecords(int $days): int
    {
        $cutoff       = Carbon::now('UTC')->subDays($days)->format('Y-m-d H:i:s');
        $totalDeleted = 0;

        do {
            $ids = RecentlyViewedProduct::where('viewed_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(1000)
                ->pluck('id')
                ->all();

            if (empty($ids)) break;

            $deleted = RecentlyViewedProduct::whereIn('id', $ids)
                ->where('viewed_at', '<', $cutoff)
                ->delete();

            $totalDeleted += $deleted;
        } while (count($ids) >= 1000);

        return $totalDeleted;
    }

    public function pruneExpiredMergeRecords(): int
    {
        $cutoff       = Carbon::now('UTC')->subDays(7)->format('Y-m-d H:i:s');
        $totalDeleted = 0;

        do {
            $ids = RecentlyViewedMergeRecord::where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(500)
                ->pluck('id')
                ->all();

            if (empty($ids)) break;

            $deleted = RecentlyViewedMergeRecord::whereIn('id', $ids)->delete();
            $totalDeleted += $deleted;
        } while (count($ids) >= 500);

        return $totalDeleted;
    }

    private function upsertMonotonic(int $customerId, int $productId, string $formattedTs): void
    {
        $this->batchUpsertMonotonic($customerId, [
            [
                'product_id' => $productId,
                'viewed_at'  => Carbon::parse($formattedTs),
            ],
        ]);
    }

    /**
     * Option B: Two-Stage Customer-Scoped Read-Then-Write with Safe Duplicate Reconciliation
     * Eliminates clustered index supremum next-key gap locks while preserving
     * customer serialization, monotonic viewed_at advancement, surrogate PK,
     * and safe recovery from concurrent duplicate-key insertion races.
     */
    private function batchUpsertMonotonic(int $customerId, array $items): void
    {
        if (empty($items)) {
            return;
        }

        // Deduplicate incoming items by product_id, keeping the newest timestamp
        $dedupedItems = [];
        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $formattedTs = $item['viewed_at'] instanceof Carbon
                ? $item['viewed_at']->format('Y-m-d H:i:s')
                : Carbon::parse($item['viewed_at'])->format('Y-m-d H:i:s');

            if (!isset($dedupedItems[$pid]) || $formattedTs > $dedupedItems[$pid]['viewed_at']) {
                $dedupedItems[$pid] = [
                    'product_id' => $pid,
                    'viewed_at'  => $formattedTs,
                ];
            }
        }

        if (empty($dedupedItems)) {
            return;
        }

        $incomingProductIds = array_keys($dedupedItems);

        // Stage 1: Customer-scoped existing-record lookup with row locking
        $lookupQuery = DB::table('recently_viewed_products')
            ->where('customer_id', $customerId)
            ->whereIn('product_id', $incomingProductIds);

        if (DB::getDriverName() !== 'sqlite') {
            $lookupQuery->lockForUpdate();
        }

        $existingRows = $lookupQuery->get(['id', 'product_id', 'viewed_at']);
        $existingMap  = [];
        foreach ($existingRows as $row) {
            $existingMap[(int) $row->product_id] = $row->viewed_at;
        }

        // Stage 2: Partition into updates for existing records and inserts for new records
        $toUpdate = [];
        $toInsert = [];

        foreach ($dedupedItems as $pid => $item) {
            $formattedTs = $item['viewed_at'];

            if (isset($existingMap[$pid])) {
                $storedTs = is_string($existingMap[$pid])
                    ? $existingMap[$pid]
                    : Carbon::parse($existingMap[$pid])->format('Y-m-d H:i:s');

                // Monotonic advancement: only update if incoming timestamp is newer
                if ($formattedTs > $storedTs) {
                    $toUpdate[] = [
                        'product_id' => $pid,
                        'viewed_at'  => $formattedTs,
                    ];
                }
            } else {
                $toInsert[] = [
                    'customer_id' => $customerId,
                    'product_id'  => $pid,
                    'viewed_at'   => $formattedTs,
                ];
            }
        }

        // Execute customer-scoped monotonic updates on existing rows
        foreach ($toUpdate as $upd) {
            DB::table('recently_viewed_products')
                ->where('customer_id', $customerId)
                ->where('product_id', $upd['product_id'])
                ->where('viewed_at', '<', $upd['viewed_at'])
                ->update(['viewed_at' => $upd['viewed_at']]);
        }

        // Execute plain INSERT for genuinely new rows
        if (!empty($toInsert)) {
            try {
                DB::table('recently_viewed_products')->insert($toInsert);
            } catch (QueryException $e) {
                if (!$this->isDuplicateKeyException($e)) {
                    throw $e;
                }

                // Stage 3: Safe Reconciliation on genuine duplicate key race
                $this->reconcileDuplicateCandidates($customerId, $toInsert);
            }
        }
    }

    /**
     * Safely reconcile candidates when a multi-row insert encounters a duplicate-key conflict.
     * Guarantees every candidate is either monotonically updated or inserted without dropping data.
     */
    private function reconcileDuplicateCandidates(int $customerId, array $candidates): void
    {
        $candidateIds = array_map(fn($c) => (int) $c['product_id'], $candidates);

        // Re-read current database state with row locking
        $reQuery = DB::table('recently_viewed_products')
            ->where('customer_id', $customerId)
            ->whereIn('product_id', $candidateIds);

        if (DB::getDriverName() !== 'sqlite') {
            $reQuery->lockForUpdate();
        }

        $freshExisting = $reQuery->get(['id', 'product_id', 'viewed_at']);
        $freshMap = [];
        foreach ($freshExisting as $row) {
            $freshMap[(int) $row->product_id] = $row->viewed_at;
        }

        foreach ($candidates as $cand) {
            $pid = (int) $cand['product_id'];
            $candTs = $cand['viewed_at'];

            if (isset($freshMap[$pid])) {
                // Record now exists: apply monotonic timestamp advancement
                $storedTs = is_string($freshMap[$pid])
                    ? $freshMap[$pid]
                    : Carbon::parse($freshMap[$pid])->format('Y-m-d H:i:s');

                if ($candTs > $storedTs) {
                    DB::table('recently_viewed_products')
                        ->where('customer_id', $customerId)
                        ->where('product_id', $pid)
                        ->where('viewed_at', '<', $candTs)
                        ->update(['viewed_at' => $candTs]);
                }
            } else {
                // Record is genuinely missing: insert individually with targeted duplicate fallback
                try {
                    DB::table('recently_viewed_products')->insert([
                        'customer_id' => $customerId,
                        'product_id'  => $pid,
                        'viewed_at'   => $candTs,
                    ]);
                } catch (QueryException $subEx) {
                    if ($this->isDuplicateKeyException($subEx)) {
                        DB::table('recently_viewed_products')
                            ->where('customer_id', $customerId)
                            ->where('product_id', $pid)
                            ->where('viewed_at', '<', $candTs)
                            ->update(['viewed_at' => $candTs]);
                    } else {
                        throw $subEx;
                    }
                }
            }
        }
    }

    /**
     * Driver-aware detection of duplicate-key constraints on recently_viewed_products.
     * Prevents non-duplicate integrity violations (e.g. Foreign Key 1452) or unrelated
     * constraints from being misclassified as duplicate entry conflicts.
     */
    private function isDuplicateKeyException(QueryException $e): bool
    {
        $driver = DB::getDriverName();
        $errorCode = (int) ($e->errorInfo[1] ?? 0);
        $msg = $e->getMessage();

        if ($driver === 'mysql') {
            // MySQL error 1062 = ER_DUP_ENTRY on uk_rvp_customer_product
            return $errorCode === 1062 && (
                str_contains($msg, 'uk_rvp_customer_product') ||
                str_contains($msg, 'recently_viewed_products') ||
                str_contains($msg, 'Duplicate entry')
            );
        }

        if ($driver === 'sqlite') {
            // SQLite 19 = SQLITE_CONSTRAINT, 2067 = SQLITE_CONSTRAINT_UNIQUE
            return ($errorCode === 19 || $errorCode === 2067)
                && str_contains($msg, 'UNIQUE constraint failed')
                && str_contains($msg, 'recently_viewed_products');
        }

        return $errorCode === 1062 || str_contains($msg, 'Duplicate entry');
    }

    private function trimToLimit(int $customerId, int $limit = 20): void
    {
        // D-02: Enforce strict retention invariant (COUNT <= $limit) in chunks of 100.
        // If customer has <= limit items, offset() returns empty and skips the DELETE entirely.
        // If customer has > limit items, deletes surplus rows iteratively until exactly $limit items remain.
        do {
            $surplusIds = RecentlyViewedProduct::where('customer_id', $customerId)
                ->orderBy('viewed_at', 'desc')
                ->orderBy('id', 'desc')
                ->offset($limit)
                ->limit(100)
                ->pluck('id')
                ->all();

            if (empty($surplusIds)) {
                break;
            }

            RecentlyViewedProduct::where('customer_id', $customerId)
                ->whereIn('id', $surplusIds)
                ->delete();
        } while (count($surplusIds) === 100);
    }
}

