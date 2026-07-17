<?php

namespace App\Support;

use App\Exceptions\UnsafeTestDatabaseException;
use Illuminate\Contracts\Foundation\Application;

/**
 * Phase 3.9 — fail-fast database isolation for browser E2E commands/servers.
 */
class E2EDatabaseGuard
{
    /**
     * @param  list<string>  $blockedDatabases
     */
    public static function assertSafe(
        string $appEnv,
        string $connection,
        string $database,
        string $approvedDatabase = 'cmart_e2e_db',
        array $blockedDatabases = [],
    ): void {
        if ($appEnv !== 'e2e') {
            throw new UnsafeTestDatabaseException(
                'app_env_not_e2e',
                'Unsafe E2E database configuration: APP_ENV must be "e2e". No database operation was performed.',
                $database !== '' ? $database : null,
                $approvedDatabase,
            );
        }

        if ($connection !== 'mysql') {
            throw new UnsafeTestDatabaseException(
                'connection_not_approved',
                'Unsafe E2E database configuration: only the mysql connection is approved. No database operation was performed.',
                $database !== '' ? $database : null,
                $approvedDatabase,
            );
        }

        if (trim($database) === '') {
            throw new UnsafeTestDatabaseException(
                'database_name_empty',
                'Unsafe E2E database configuration: DB_DATABASE is empty. No database operation was performed.',
                null,
                $approvedDatabase,
            );
        }

        $normalized = strtolower(trim($database));
        $blocked = array_map(
            fn (string $name) => strtolower(trim($name)),
            array_merge(['cmart', 'cmart_db', 'production', 'prod', 'staging'], $blockedDatabases),
        );

        if (in_array($normalized, $blocked, true)) {
            throw new UnsafeTestDatabaseException(
                'blocked_database_name',
                sprintf(
                    'Unsafe E2E database configuration: database "%s" is blocked. No database operation was performed.',
                    $database,
                ),
                $database,
                $approvedDatabase,
            );
        }

        if ($normalized !== strtolower($approvedDatabase)) {
            throw new UnsafeTestDatabaseException(
                'database_not_approved',
                sprintf(
                    'Unsafe E2E database configuration: database "%s" is not the approved isolated database "%s". No database operation was performed.',
                    $database,
                    $approvedDatabase,
                ),
                $database,
                $approvedDatabase,
            );
        }
    }

    public static function assertSafeFromApplication(Application $app): void
    {
        if (! $app->environment('e2e')) {
            return;
        }

        $config = $app->make('config');
        $connection = (string) $config->get('database.default', '');
        $database = (string) $config->get("database.connections.{$connection}.database", '');

        self::assertSafe(
            appEnv: (string) $app->environment(),
            connection: $connection,
            database: $database,
            approvedDatabase: (string) $config->get('e2e.approved_database', 'cmart_e2e_db'),
            blockedDatabases: array_map('strval', $config->get('e2e.blocked_databases', [])),
        );
    }
}
