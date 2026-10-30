<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Tests;

use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\LexiconServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LexiconServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Lexicon' => Lexicon::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', self::connection());
        $app['config']->set('cache.default', 'array');
    }

    /**
     * In-memory SQLite by default. CI points the suite at MySQL or
     * PostgreSQL with LEXICON_TEST_DB=mysql|pgsql; the database name must
     * contain "test", because the persistence tests drop every table in it.
     *
     * @return array<string, mixed>
     */
    private static function connection(): array
    {
        $driver = getenv('LEXICON_TEST_DB') ?: 'sqlite';

        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
        }

        $database = (string) getenv('LEXICON_TEST_DB_DATABASE');

        if (! str_contains($database, 'test')) {
            throw new \RuntimeException("Refusing to run against database [{$database}]: its name must contain 'test'.");
        }

        return [
            'driver' => $driver,
            'host' => getenv('LEXICON_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('LEXICON_TEST_DB_PORT') ?: ($driver === 'mysql' ? '3306' : '5432'),
            'database' => $database,
            'username' => getenv('LEXICON_TEST_DB_USERNAME') ?: 'root',
            'password' => getenv('LEXICON_TEST_DB_PASSWORD') ?: '',
            'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
            // A deliberately accent-insensitive default, to prove keys do not depend on it.
            'collation' => $driver === 'mysql' ? 'utf8mb4_unicode_ci' : null,
            'prefix' => '',
        ];
    }
}
