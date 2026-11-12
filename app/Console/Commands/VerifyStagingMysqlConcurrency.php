<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PayoutService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class VerifyStagingMysqlConcurrency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:mysql-concurrency
        {--iterations=5 : Number of test iterations (1-100)}
        {--worker : Internal flag for parallel worker execution}
        {--payout-id= : Target payout request ID for worker mode}
        {--manager-id= : Target manager user ID for worker mode}
        {--transfer-ref= : Bank transfer reference for worker mode}
        {--barrier-dir= : Synchronization barrier directory path}
        {--worker-id= : Worker identifier (1 or 2)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify MySQL 8 / InnoDB row-level locking and unique constraint enforcement under genuine parallel concurrency';

    /**
     * Execute the console command.
     */
    public function handle(PayoutService $payoutService): int
    {
        if (!$this->assertSafeIsolatedEnvironment((bool) $this->option('worker'))) {
            return Command::FAILURE;
        }

        if ($this->option('worker')) {
            return $this->runWorkerMode($payoutService);
        }

        if (!function_exists('proc_open')) {
            $this->error('ABORTED: PHP proc_open() is unavailable in this environment; genuine parallel concurrency workers cannot be spawned.');
            return Command::FAILURE;
        }

        $iterations = (int) $this->option('iterations');
        if ($iterations < 1 || $iterations > 100) {
            $this->error('ABORTED: --iterations must be between 1 and 100.');
            return Command::FAILURE;
        }

        $this->info("Starting Genuine Parallel MySQL 8 / InnoDB Concurrency Verification ({$iterations} iterations)...");

        $passed = 0;
        $cleanupFailed = false;

        for ($i = 1; $i <= $iterations; $i++) {
            $this->line("--- Iteration {$i}/{$iterations} ---");

            $tracked = [
                'user_ids' => [],
                'profile_ids' => [],
                'payout_ids' => [],
            ];

            try {
                $iterationPassed = $this->executeIteration($i, $payoutService, $tracked);
                if ($iterationPassed) {
                    $passed++;
                }
            } catch (Throwable $e) {
                $this->error("✗ Iteration {$i} FAILED with exception: {$e->getMessage()}");
            } finally {
                try {
                    $this->cleanupTrackedRecords($tracked);
                } catch (Throwable $cleanupException) {
                    $cleanupFailed = true;
                    $this->error("✗ CLEANUP FAILURE on Iteration {$i}: {$cleanupException->getMessage()}");
                }
            }
        }

        $this->newLine();

        if ($cleanupFailed) {
            $this->error('CERTIFICATION FAILED: Cleanup errors occurred during verification.');
            return Command::FAILURE;
        }

        if ($passed === $iterations) {
            $this->info("All {$iterations} parallel MySQL 8 / InnoDB concurrency iterations PASSED with clean database state.");
            return Command::SUCCESS;
        }

        $this->error("{$passed}/{$iterations} iterations passed. Concurrency verification FAILED.");
        return Command::FAILURE;
    }

    /**
     * Enforce absolute production and database isolation guards before any write occurs.
     */
    public function assertSafeIsolatedEnvironment(bool $quiet = false): bool
    {
        if (app()->environment('production')) {
            $this->emitSafetyError('ABORTED: test:mysql-concurrency is strictly prohibited in production environments.', $quiet);
            return false;
        }

        $connectionName = (string) config('database.default');
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();

        if ($driver !== 'mysql') {
            $this->emitSafetyError(
                "ABORTED: test:mysql-concurrency requires the 'mysql' driver (detected connection '{$connectionName}', driver '{$driver}').",
                $quiet
            );
            return false;
        }

        $configuredDb = (string) config("database.connections.{$connectionName}.database");
        if (!str_ends_with($configuredDb, '_test')) {
            $this->emitSafetyError(
                "ABORTED: Configured database '{$configuredDb}' must end with '_test' to prevent running against staging or production data.",
                $quiet
            );
            return false;
        }

        try {
            $actualRow = $connection->selectOne('SELECT DATABASE() AS current_db');
            $actualDb = (string) ($actualRow->current_db ?? $connection->getDatabaseName());
        } catch (Throwable $e) {
            $this->emitSafetyError('ABORTED: Failed to verify active MySQL database identity: ' . $e->getMessage(), $quiet);
            return false;
        }

        if (!str_ends_with($actualDb, '_test') || $actualDb !== $configuredDb) {
            $this->emitSafetyError(
                "ABORTED: Connected database '{$actualDb}' failed isolation verification (expected '{$configuredDb}' ending with '_test').",
                $quiet
            );
            return false;
        }

        if (!$quiet) {
            $this->info("Verified Isolated Target -> Connection: {$connectionName} | Driver: {$driver} | Database: {$actualDb}");
        }

        return true;
    }

    /**
     * Emit either a standard CLI error or JSON error payload in worker mode.
     */
    private function emitSafetyError(string $message, bool $quiet): void
    {
        if ($quiet) {
            $this->line((string) json_encode(['status' => 'error', 'message' => $message]));
            return;
        }

        $this->error($message);
    }

    /**
     * Execute one full iteration testing both InnoDB row-lock and unique-constraint contention.
     *
     * @param array{user_ids: array<int, int>, profile_ids: array<int, int>, payout_ids: array<int, int>} $tracked
     */
    private function executeIteration(int $iteration, PayoutService $payoutService, array &$tracked): bool
    {
        $suffix = $iteration . '_' . uniqid('', true);

        $testAgent = User::create([
            'name' => "Staging Test Agent {$iteration}",
            'email' => "staging_agent_{$suffix}@test.local",
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'password' => bcrypt('TestSecret123!'),
        ]);
        $tracked['user_ids'][] = $testAgent->id;

        $profile = AgentProfile::create([
            'user_id' => $testAgent->id,
            'business_name' => "Staging Test Business {$iteration}",
            'bank_name' => 'Test Bank',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Test Agent',
            'kyc_status' => 'verified',
            'wallet_balance' => '50000.00',
            'reserved_balance' => '0.00',
        ]);
        $tracked['profile_ids'][] = $profile->id;

        $testManager = User::create([
            'name' => "Staging Accountant {$iteration}",
            'email' => "staging_acct_{$suffix}@test.local",
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
            'password' => bcrypt('TestSecret123!'),
        ]);
        $tracked['user_ids'][] = $testManager->id;

        $testOwner = User::create([
            'name' => "Staging Owner {$iteration}",
            'email' => "staging_owner_{$suffix}@test.local",
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'password' => bcrypt('TestSecret123!'),
        ]);
        $tracked['user_ids'][] = $testOwner->id;

        $payout1 = $payoutService->createPayoutRequest($testAgent, '10000.00');
        $tracked['payout_ids'][] = $payout1->id;

        $payout2 = $payoutService->createPayoutRequest($testAgent, '10000.00');
        $tracked['payout_ids'][] = $payout2->id;

        $payout3 = $payoutService->createPayoutRequest($testAgent, '10000.00');
        $tracked['payout_ids'][] = $payout3->id;

        $payoutService->approvePayoutRequest($payout1, $testOwner);
        $payoutService->approvePayoutRequest($payout2, $testOwner);
        $payoutService->approvePayoutRequest($payout3, $testOwner);

        // Phase A: Parallel InnoDB Row-Lock Race (Two workers racing on the SAME payout)
        $refA1 = 'STG-ROW-A1-' . strtoupper(uniqid());
        $refA2 = 'STG-ROW-A2-' . strtoupper(uniqid());

        $rowRaceResults = $this->runParallelWorkerRace([
            ['payout_id' => $payout1->id, 'manager_id' => $testManager->id, 'transfer_ref' => $refA1],
            ['payout_id' => $payout1->id, 'manager_id' => $testManager->id, 'transfer_ref' => $refA2],
        ]);

        if (!$this->verifySingleWinnerConflict($rowRaceResults, 'Phase A (Same Payout Row-Lock Race)')) {
            return false;
        }

        $payout1->refresh();
        $profile->refresh();

        $payout1CompletedTx = WalletTransaction::where('source_type', PayoutRequest::class)
            ->where('source_id', $payout1->id)
            ->where('transaction_type', WalletTransaction::TYPE_PAYOUT_COMPLETED)
            ->count();

        if (
            $payout1->status !== PayoutRequest::STATUS_PAID
            || (string) $profile->wallet_balance !== '40000.00'
            || (string) $profile->reserved_balance !== '20000.00'
            || $payout1CompletedTx !== 1
        ) {
            $this->error("✗ Phase A integrity mismatch (status={$payout1->status}, wallet={$profile->wallet_balance}, reserved={$profile->reserved_balance}, tx={$payout1CompletedTx}).");
            return false;
        }

        // Phase B: Parallel MySQL Unique Index Race (Two workers racing on DIFFERENT payouts with SAME reference)
        $raceRef = 'STG-UNQ-' . strtoupper(uniqid());
        $raceRefVariant = '  ' . strtolower($raceRef) . '  ';

        $uniqueRaceResults = $this->runParallelWorkerRace([
            ['payout_id' => $payout2->id, 'manager_id' => $testManager->id, 'transfer_ref' => $raceRef],
            ['payout_id' => $payout3->id, 'manager_id' => $testManager->id, 'transfer_ref' => $raceRefVariant],
        ]);

        if (!$this->verifySingleWinnerConflict($uniqueRaceResults, 'Phase B (Unique Constraint Race)')) {
            return false;
        }

        $payout2->refresh();
        $payout3->refresh();
        $profile->refresh();

        $paidCount = collect([$payout2, $payout3])->where('status', PayoutRequest::STATUS_PAID)->count();
        $approvedCount = collect([$payout2, $payout3])->where('status', PayoutRequest::STATUS_APPROVED)->count();
        $unpaidPayout = $payout2->status === PayoutRequest::STATUS_APPROVED ? $payout2 : $payout3;

        $phaseBTxCount = WalletTransaction::where('source_type', PayoutRequest::class)
            ->whereIn('source_id', [$payout2->id, $payout3->id])
            ->where('transaction_type', WalletTransaction::TYPE_PAYOUT_COMPLETED)
            ->count();

        $totalAgentCompletedTx = WalletTransaction::where('agent_id', $testAgent->id)
            ->where('transaction_type', WalletTransaction::TYPE_PAYOUT_COMPLETED)
            ->count();

        $isPhaseBValid = $paidCount === 1
            && $approvedCount === 1
            && $unpaidPayout->bank_transfer_reference === null
            && (string) $profile->wallet_balance === '30000.00'
            && (string) $profile->reserved_balance === '10000.00'
            && $phaseBTxCount === 1
            && $totalAgentCompletedTx === 2;

        if (!$isPhaseBValid) {
            $this->error("✗ Phase B integrity mismatch (paid={$paidCount}, approved={$approvedCount}, wallet={$profile->wallet_balance}, reserved={$profile->reserved_balance}, txB={$phaseBTxCount}, totalTx={$totalAgentCompletedTx}).");
            return false;
        }

        $this->info("✓ Iteration {$iteration} PASSED: Row lock, unique constraint, and wallet invariants verified under parallel workers.");
        return true;
    }

    /**
     * Verify that among two parallel workers, exactly one completed and one encountered a conflict.
     *
     * @param array<int, array<string, mixed>> $results
     */
    private function verifySingleWinnerConflict(array $results, string $phaseName): bool
    {
        $completed = collect($results)->where('status', 'completed')->count();
        $conflicts = collect($results)->where('status', 'conflict')->count();

        if ($completed === 1 && $conflicts === 1) {
            return true;
        }

        $this->error("✗ {$phaseName} failed concurrency assertion (completed={$completed}, conflicts={$conflicts}): " . json_encode($results));
        return false;
    }

    /**
     * Spawn two synchronized parallel worker subprocesses that open independent DB connections.
     *
     * @param array<int, array{payout_id: int, manager_id: int, transfer_ref: string}> $jobs
     * @return array<int, array<string, mixed>>
     */
    private function runParallelWorkerRace(array $jobs): array
    {
        $barrierDir = storage_path('framework/testing/concurrency_' . uniqid('', true));
        if (!@mkdir($barrierDir, 0775, true) && !is_dir($barrierDir)) {
            throw new RuntimeException("Unable to create synchronization barrier directory: {$barrierDir}");
        }

        $processes = [];

        try {
            foreach ($jobs as $index => $job) {
                $workerId = $index + 1;
                $process = new Process([
                    PHP_BINARY,
                    base_path('artisan'),
                    'test:mysql-concurrency',
                    '--worker',
                    '--payout-id=' . $job['payout_id'],
                    '--manager-id=' . $job['manager_id'],
                    '--transfer-ref=' . $job['transfer_ref'],
                    '--barrier-dir=' . $barrierDir,
                    '--worker-id=' . $workerId,
                ], base_path());
                $process->setTimeout(30);
                $process->start();
                $processes[$workerId] = $process;
            }

            // Wait until both workers have booted Laravel, checked DB isolation, and opened DB connections
            $deadline = microtime(true) + 15.0;
            while (microtime(true) < $deadline) {
                if (
                    is_file($barrierDir . DIRECTORY_SEPARATOR . 'ready_1')
                    && is_file($barrierDir . DIRECTORY_SEPARATOR . 'ready_2')
                ) {
                    break;
                }

                foreach ($processes as $workerId => $process) {
                    if (!$process->isRunning()) {
                        throw new RuntimeException(
                            "Worker {$workerId} exited prematurely before barrier: " . trim($process->getOutput() . ' ' . $process->getErrorOutput())
                        );
                    }
                }

                usleep(2000);
            }

            if (
                !is_file($barrierDir . DIRECTORY_SEPARATOR . 'ready_1')
                || !is_file($barrierDir . DIRECTORY_SEPARATOR . 'ready_2')
            ) {
                throw new RuntimeException('Synchronization barrier timeout waiting for parallel workers to become ready.');
            }

            // Release both workers simultaneously
            file_put_contents($barrierDir . DIRECTORY_SEPARATOR . 'go', (string) microtime(true));

            $results = [];
            foreach ($processes as $workerId => $process) {
                $process->wait();
                $rawOutput = trim($process->getOutput());
                $decoded = json_decode($rawOutput, true);

                if (!is_array($decoded)) {
                    $results[] = [
                        'worker' => $workerId,
                        'status' => 'error',
                        'message' => $rawOutput !== '' ? $rawOutput : trim($process->getErrorOutput()),
                    ];
                } else {
                    $decoded['worker'] = $workerId;
                    $results[] = $decoded;
                }
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }

            foreach (glob($barrierDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($barrierDir);
        }
    }

    /**
     * Execute worker half of a parallel concurrency race using its own DB connection.
     */
    private function runWorkerMode(PayoutService $payoutService): int
    {
        $payoutId = (int) $this->option('payout-id');
        $managerId = (int) $this->option('manager-id');
        $transferRef = (string) $this->option('transfer-ref');
        $barrierDir = (string) $this->option('barrier-dir');
        $workerId = (int) $this->option('worker-id');

        if ($payoutId <= 0 || $managerId <= 0 || $transferRef === '' || $barrierDir === '' || !is_dir($barrierDir)) {
            $this->line((string) json_encode(['status' => 'error', 'message' => 'Invalid worker arguments.']));
            return Command::FAILURE;
        }

        try {
            // Establish independent PDO connection before signaling ready
            DB::connection()->getPdo();

            $payout = PayoutRequest::findOrFail($payoutId);
            $manager = User::findOrFail($managerId);

            file_put_contents($barrierDir . DIRECTORY_SEPARATOR . "ready_{$workerId}", '1');

            $deadline = microtime(true) + 15.0;
            $goFile = $barrierDir . DIRECTORY_SEPARATOR . 'go';
            while (!is_file($goFile)) {
                if (microtime(true) >= $deadline) {
                    $this->line((string) json_encode(['status' => 'timeout', 'message' => 'Barrier go timeout.']));
                    return Command::FAILURE;
                }
                usleep(2000);
            }

            $payoutService->recordPayment($payout, $manager, [
                'bank_transfer_reference' => $transferRef,
            ]);

            $this->line((string) json_encode([
                'status' => 'completed',
                'payout_id' => $payoutId,
            ]));
            return Command::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->line((string) json_encode([
                'status' => 'conflict',
                'message' => $e->getMessage(),
            ]));
            return Command::SUCCESS;
        } catch (QueryException $e) {
            $message = $e->getMessage();
            $code = (string) $e->getCode();
            if ($code === '23000' || $code === '40001' || str_contains($message, 'Duplicate') || str_contains($message, 'UNIQUE') || str_contains($message, 'Deadlock')) {
                $this->line((string) json_encode([
                    'status' => 'conflict',
                    'message' => $message,
                ]));
                return Command::SUCCESS;
            }

            $this->line((string) json_encode([
                'status' => 'error',
                'message' => $message,
            ]));
            return Command::FAILURE;
        } catch (Throwable $e) {
            $this->line((string) json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]));
            return Command::FAILURE;
        }
    }

    /**
     * Clean up only the explicitly tracked synthetic test records in strict child-first FK order.
     *
     * Never invokes Eloquent User::delete() on protected Business Owner records and never
     * disables FOREIGN_KEY_CHECKS or bypasses financial model immutability guards.
     *
     * @param array{user_ids: array<int, int>, profile_ids: array<int, int>, payout_ids: array<int, int>} $tracked
     */
    public function cleanupTrackedRecords(array $tracked): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $tracked['user_ids'] ?? []))));
        $profileIds = array_values(array_unique(array_filter(array_map('intval', $tracked['profile_ids'] ?? []))));
        $payoutIds = array_values(array_unique(array_filter(array_map('intval', $tracked['payout_ids'] ?? []))));

        if (empty($userIds) && empty($profileIds) && empty($payoutIds)) {
            return;
        }

        DB::transaction(function () use ($userIds, $profileIds, $payoutIds): void {
            if (!empty($userIds)) {
                DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->whereIn('notifiable_id', $userIds)
                    ->delete();
            }

            if (!empty($userIds) || !empty($payoutIds)) {
                DB::table('audit_logs')
                    ->where(function ($query) use ($userIds, $payoutIds): void {
                        if (!empty($userIds)) {
                            $query->whereIn('user_id', $userIds);
                        }
                        if (!empty($payoutIds)) {
                            $method = !empty($userIds) ? 'orWhere' : 'where';
                            $query->{$method}(function ($subQuery) use ($payoutIds): void {
                                $subQuery->where('auditable_type', PayoutRequest::class)
                                    ->whereIn('auditable_id', $payoutIds);
                            });
                        }
                    })
                    ->delete();
            }

            if (!empty($userIds) || !empty($payoutIds)) {
                DB::table('wallet_transactions')
                    ->where(function ($query) use ($userIds, $payoutIds): void {
                        if (!empty($userIds)) {
                            $query->whereIn('agent_id', $userIds);
                        }
                        if (!empty($payoutIds)) {
                            $method = !empty($userIds) ? 'orWhere' : 'where';
                            $query->{$method}(function ($subQuery) use ($payoutIds): void {
                                $subQuery->where('source_type', PayoutRequest::class)
                                    ->whereIn('source_id', $payoutIds);
                            });
                        }
                    })
                    ->delete();
            }

            if (!empty($payoutIds)) {
                DB::table('payout_requests')->whereIn('id', $payoutIds)->delete();
            }

            if (!empty($profileIds)) {
                DB::table('agent_profiles')->whereIn('id', $profileIds)->delete();
            }

            if (!empty($userIds)) {
                DB::table('users')->whereIn('id', $userIds)->delete();
            }
        });

        // Verify zero tracked leftovers remain
        $leftoverUsers = !empty($userIds) ? DB::table('users')->whereIn('id', $userIds)->count() : 0;
        $leftoverProfiles = !empty($profileIds) ? DB::table('agent_profiles')->whereIn('id', $profileIds)->count() : 0;
        $leftoverPayouts = !empty($payoutIds) ? DB::table('payout_requests')->whereIn('id', $payoutIds)->count() : 0;

        if ($leftoverUsers > 0 || $leftoverProfiles > 0 || $leftoverPayouts > 0) {
            throw new RuntimeException(
                "Synthetic cleanup verification failed (leftover users={$leftoverUsers}, profiles={$leftoverProfiles}, payouts={$leftoverPayouts})."
            );
        }
    }
}
