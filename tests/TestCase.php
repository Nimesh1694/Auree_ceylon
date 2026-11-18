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
     * Prevent destructive test runs from touching non-SQLite databases other than the
     * explicitly authorized disposable test database.
     */
    public static function assertIsolatedTestDatabase(): void
    {
        $defaultConnection = (string) config('database.default');

        if ($defaultConnection === 'sqlite') {
            return;
        }

        $dbName = (string) config("database.connections.{$defaultConnection}.database");

        if ($dbName !== 'auraluxe_disposable_test') {
            throw new RuntimeException(
                "SAFETY ABORT: Non-SQLite test execution refused. Only the authorized disposable database 'auraluxe_disposable_test' is permitted."
            );
        }
    }
}
