<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Enforce database isolation before any test traits (such as RefreshDatabase) execute.
     */
    protected function setUpTraits()
    {
        static::assertIsolatedTestDatabase();

        return parent::setUpTraits();
    }

    /**
     * Prevent destructive test runs from touching non-SQLite databases that do not end with "_test".
     */
    public static function assertIsolatedTestDatabase(): void
    {
        $defaultConnection = (string) config('database.default');

        if ($defaultConnection === 'sqlite') {
            return;
        }

        $dbName = (string) config("database.connections.{$defaultConnection}.database");

        if ($dbName !== ':memory:' && !str_ends_with($dbName, '_test')) {
            throw new RuntimeException(
                "SAFETY ABORT: Non-SQLite test execution refused because database '{$dbName}' on connection '{$defaultConnection}' does not end with '_test'."
            );
        }
    }
}
