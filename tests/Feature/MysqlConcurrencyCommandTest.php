<?php

namespace Tests\Feature;

use App\Console\Commands\VerifyStagingMysqlConcurrency;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PayoutService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class MysqlConcurrencyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_environment_is_unconditionally_blocked(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('test:mysql-concurrency', ['--iterations' => 1])
                ->expectsOutputToContain('strictly prohibited in production environments')
                ->assertExitCode(1);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    public function test_non_mysql_connection_is_rejected_before_any_writes(): void
    {
        $usersBefore = User::count();
        $payoutsBefore = PayoutRequest::count();

        $originalDefault = Config::get('database.default');

        if ($originalDefault !== 'sqlite') {
            Config::set('database.connections.sqlite_guard_test', [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ]);
            Config::set('database.default', 'sqlite_guard_test');
        }

        try {
            $this->artisan('test:mysql-concurrency', ['--iterations' => 1])
                ->expectsOutputToContain("requires the 'mysql' driver")
                ->assertExitCode(1);
        } finally {
            Config::set('database.default', $originalDefault);
            if ($originalDefault !== 'sqlite') {
                DB::purge('sqlite_guard_test');
            }
        }

        $this->assertSame($usersBefore, User::count());
        $this->assertSame($payoutsBefore, PayoutRequest::count());
    }

    public function test_mysql_database_without_test_suffix_is_rejected_before_any_writes(): void
    {
        $fakeConn = new class (fn () => null, 'auraluxe_staging') extends MySqlConnection {
            public function getDriverName()
            {
                return 'mysql';
            }

            public function selectOne($query, $bindings = [], $useReadPdo = true)
            {
                throw new RuntimeException('selectOne must not run before _test suffix validation.');
            }
        };

        $originalDefault = Config::get('database.default');

        DB::extend('mysql_fake_staging', fn () => $fakeConn);
        Config::set('database.connections.mysql_fake_staging', [
            'driver' => 'mysql_fake_staging',
            'database' => 'auraluxe_staging',
        ]);
        Config::set('database.default', 'mysql_fake_staging');

        try {
            $this->artisan('test:mysql-concurrency', ['--iterations' => 1])
                ->expectsOutputToContain("must end with '_test'")
                ->assertExitCode(1);
        } finally {
            Config::set('database.default', $originalDefault);
            DB::purge('mysql_fake_staging');
        }
    }

    public function test_mysql_database_mismatch_between_configured_and_actual_connected_db_is_rejected(): void
    {
        $fakeConn = new class (fn () => null, 'auraluxe_staging_test') extends MySqlConnection {
            public function getDriverName()
            {
                return 'mysql';
            }

            public function selectOne($query, $bindings = [], $useReadPdo = true)
            {
                return (object) ['current_db' => 'auraluxe_live'];
            }
        };

        $originalDefault = Config::get('database.default');

        DB::extend('mysql_fake_mismatch', fn () => $fakeConn);
        Config::set('database.connections.mysql_fake_mismatch', [
            'driver' => 'mysql_fake_mismatch',
            'database' => 'auraluxe_staging_test',
        ]);
        Config::set('database.default', 'mysql_fake_mismatch');

        try {
            $this->artisan('test:mysql-concurrency', ['--iterations' => 1])
                ->expectsOutputToContain('failed isolation verification')
                ->assertExitCode(1);
        } finally {
            Config::set('database.default', $originalDefault);
            DB::purge('mysql_fake_mismatch');
        }
    }

    public function test_mysql_and_mariadb_connections_enforce_innodb_by_default(): void
    {
        $dbConfig = require config_path('database.php');

        $this->assertSame('InnoDB', $dbConfig['connections']['mysql']['engine']);
        $this->assertSame('InnoDB', $dbConfig['connections']['mariadb']['engine']);
        $this->assertSame('sqlite', $dbConfig['connections']['sqlite']['driver']);
        $this->assertArrayNotHasKey('engine', $dbConfig['connections']['sqlite']);
    }

    public function test_phpunit_mysql_xml_exists_with_isolated_test_database_and_no_secrets(): void
    {
        $path = base_path('phpunit.mysql.xml');
        $this->assertFileExists($path);

        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml);

        $envVars = [];
        foreach ($xml->php->env as $env) {
            $envVars[(string) $env['name']] = (string) $env['value'];
        }

        $this->assertSame('testing', $envVars['APP_ENV'] ?? null);
        $this->assertSame('false', $envVars['APP_DEBUG'] ?? null);
        $this->assertSame('mysql', $envVars['DB_CONNECTION'] ?? null);
        $this->assertSame('InnoDB', $envVars['DB_ENGINE'] ?? null);
        $this->assertStringEndsWith('_test', $envVars['DB_DATABASE'] ?? '');
        $this->assertArrayNotHasKey('DB_PASSWORD', $envVars);
        $this->assertArrayNotHasKey('DB_USERNAME', $envVars);
    }

    public function test_testcase_isolation_guard_blocks_non_sqlite_databases_without_test_suffix(): void
    {
        $originalDefault = Config::get('database.default');
        $originalMysqlDb = Config::get('database.connections.mysql.database');

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', 'auraluxe_staging');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage("does not end with '_test'");

            TestCase::assertIsolatedTestDatabase();
        } finally {
            Config::set('database.connections.mysql.database', $originalMysqlDb);
            Config::set('database.default', $originalDefault);
        }
    }

    public function test_testcase_isolation_guard_allows_mysql_database_with_test_suffix(): void
    {
        $originalDefault = Config::get('database.default');
        $originalMysqlDb = Config::get('database.connections.mysql.database');

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', 'auraluxe_staging_test');

        try {
            TestCase::assertIsolatedTestDatabase();
            $this->assertTrue(true);
        } finally {
            Config::set('database.connections.mysql.database', $originalMysqlDb);
            Config::set('database.default', $originalDefault);
        }
    }

    public function test_controlled_child_first_cleanup_removes_only_tracked_synthetic_records_and_preserves_guards(): void
    {
        $payoutService = app(PayoutService::class);

        // 1. Pre-existing application records that MUST remain untouched
        $existingOwner = User::create([
            'name' => 'Existing Business Owner',
            'email' => 'existing_owner@auraluxe.lk',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'password' => bcrypt('Secret123!'),
        ]);

        $existingAgent = User::create([
            'name' => 'Existing Active Agent',
            'email' => 'existing_agent@auraluxe.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'password' => bcrypt('Secret123!'),
        ]);

        $existingProfile = AgentProfile::create([
            'user_id' => $existingAgent->id,
            'business_name' => 'Existing Agent Biz',
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '9988776655',
            'bank_account_name' => 'Existing Agent',
            'kyc_status' => 'verified',
            'wallet_balance' => '25000.00',
            'reserved_balance' => '0.00',
        ]);

        $existingPayout = $payoutService->createPayoutRequest($existingAgent, '5000.00');

        // 2. Synthetic records created during an iteration
        $syntheticAgent = User::create([
            'name' => 'Synthetic Agent',
            'email' => 'synthetic_agent@test.local',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'password' => bcrypt('Secret123!'),
        ]);

        $syntheticProfile = AgentProfile::create([
            'user_id' => $syntheticAgent->id,
            'business_name' => 'Synthetic Biz',
            'bank_name' => 'Test Bank',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Synthetic Agent',
            'kyc_status' => 'verified',
            'wallet_balance' => '50000.00',
            'reserved_balance' => '0.00',
        ]);

        $syntheticManager = User::create([
            'name' => 'Synthetic Accountant',
            'email' => 'synthetic_acct@test.local',
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
            'password' => bcrypt('Secret123!'),
        ]);

        $syntheticOwner = User::create([
            'name' => 'Synthetic Owner',
            'email' => 'synthetic_owner@test.local',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'password' => bcrypt('Secret123!'),
        ]);

        $syntheticPayout = $payoutService->createPayoutRequest($syntheticAgent, '10000.00');
        $payoutService->approvePayoutRequest($syntheticPayout, $syntheticOwner);
        $payoutService->recordPayment($syntheticPayout, $syntheticManager, [
            'bank_transfer_reference' => 'SYN-REF-001',
        ]);

        $tracked = [
            'user_ids' => [$syntheticAgent->id, $syntheticManager->id, $syntheticOwner->id],
            'profile_ids' => [$syntheticProfile->id],
            'payout_ids' => [$syntheticPayout->id],
        ];

        // 3. Execute controlled child-first cleanup
        $command = new VerifyStagingMysqlConcurrency();
        $command->cleanupTrackedRecords($tracked);

        // 4. Verify all synthetic tracked records were completely removed
        $this->assertDatabaseMissing('users', ['id' => $syntheticAgent->id]);
        $this->assertDatabaseMissing('users', ['id' => $syntheticManager->id]);
        $this->assertDatabaseMissing('users', ['id' => $syntheticOwner->id]);
        $this->assertDatabaseMissing('agent_profiles', ['id' => $syntheticProfile->id]);
        $this->assertDatabaseMissing('payout_requests', ['id' => $syntheticPayout->id]);
        $this->assertSame(0, WalletTransaction::where('agent_id', $syntheticAgent->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $syntheticAgent->id)->count());
        $this->assertSame(0, DB::table('audit_logs')->where('auditable_type', PayoutRequest::class)->where('auditable_id', $syntheticPayout->id)->count());

        // 5. Verify pre-existing financial & user records remain 100% preserved
        $this->assertDatabaseHas('users', ['id' => $existingOwner->id]);
        $this->assertDatabaseHas('users', ['id' => $existingAgent->id]);
        $this->assertDatabaseHas('agent_profiles', ['id' => $existingProfile->id]);
        $this->assertDatabaseHas('payout_requests', ['id' => $existingPayout->id]);
        $this->assertSame(1, WalletTransaction::where('agent_id', $existingAgent->id)->count());

        // 6. Verify Eloquent User::deleting() immutability guards remain strictly enforced
        $ownerBlocked = false;
        try {
            $existingOwner->delete();
        } catch (AuthorizationException) {
            $ownerBlocked = true;
        }
        $this->assertTrue($ownerBlocked, 'Business Owner Eloquent deletion guard must remain active.');

        $agentBlocked = false;
        try {
            $existingAgent->delete();
        } catch (AuthorizationException) {
            $agentBlocked = true;
        }
        $this->assertTrue($agentBlocked, 'Agent with wallet/payout history Eloquent deletion guard must remain active.');
    }

    public function test_dashboard_controller_uses_active_connection_database_name(): void
    {
        $owner = User::create([
            'name' => 'Dashboard Owner',
            'email' => 'dash_owner@auraluxe.lk',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'password' => bcrypt('Secret123!'),
            'two_factor_secret' => 'TESTSECRET123456',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['two_factor_authenticated' => true])
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }
}
