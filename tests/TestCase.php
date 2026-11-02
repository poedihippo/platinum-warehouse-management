<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * `DatabaseManager::configuration()` pipes every connection config through
     * `ConfigurationUrlParser`, so a DATABASE_URL / DB_URL inherited from the shell
     * silently overrides the driver, host, port and database of every connection.
     * Neither `.env` nor phpunit.xml define them, so dropping them is safe.
     */
    public function createApplication(): Application
    {
        foreach (['DATABASE_URL', 'DB_URL'] as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        return parent::createApplication();
    }

    /**
     * Runs after the config is loaded and before RefreshDatabase migrates, so the
     * suite can never drop a database whose name does not end with "_test".
     */
    public function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        if ($driver !== 'mysql' || ! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Refusing to run: the "%s" connection points at %s database "%s"; the suite only runs against a MySQL database whose name ends with "_test".',
                (string) $connection,
                (string) $driver,
                (string) $database
            ));
        }
    }
}
