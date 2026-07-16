<?php

namespace App\Support;

use App\Exceptions\UnsafeTestDatabaseException;
use Illuminate\Contracts\Foundation\Application;

/**
 * Phase 3.3 — fail-fast guard for PHPUnit and database-aware test bootstrapping.
 *
 * Pure validation methods do not open database connections.
 */
class TestingDatabaseGuard
{
    /**
     * @param  list<string>  $blockedDatabases
     * @param  list<string>  $approvedConnections
     */
    public static function assertSafe(
        string $appEnv,
        string $connection,
        string $database,
        string $approvedDatabase,
        ?string $developmentDatabase = null,
        array $blockedDatabases = [],
        array $approvedConnections = ['mysql'],
    ): void {
        if ($appEnv !== 'testing') {
            throw new UnsafeTestDatabaseException(
                'app_env_not_testing',
                sprintf(
                    'Unsafe test database configuration: APP_ENV must be "testing" but "%s" was resolved. No database operation was performed.',
                    $appEnv === '' ? '(empty)' : $appEnv,
                ),
                $database === '' ? null : $database,
                $approvedDatabase,
            );
        }

        if (! in_array($connection, $approvedConnections, true)) {
            throw new UnsafeTestDatabaseException(
                'connection_not_approved',
                sprintf(
                    'Unsafe test database configuration: connection "%s" is not approved for PHPUnit. Use mysql against the disposable test database "%s". No database operation was performed.',
                    $connection === '' ? '(empty)' : $connection,
                    $approvedDatabase,
                ),
                $database === '' ? null : $database,
                $approvedDatabase,
            );
        }

        if (trim($database) === '') {
            throw new UnsafeTestDatabaseException(
                'database_name_empty',
                sprintf(
                    'Unsafe test database configuration: DB_DATABASE resolved to an empty value. Configure DB_DATABASE=%s in phpunit.xml and .env.testing. No database operation was performed.',
                    $approvedDatabase,
                ),
                null,
                $approvedDatabase,
            );
        }

        $normalizedDatabase = strtolower($database);

        if ($developmentDatabase !== null && strtolower($developmentDatabase) === $normalizedDatabase) {
            throw new UnsafeTestDatabaseException(
                'development_database_resolved',
                sprintf(
                    'Unsafe test database configuration: resolved database "%s" matches the persistent development database. PHPUnit must use "%s" instead. No database operation was performed.',
                    $database,
                    $approvedDatabase,
                ),
                $database,
                $approvedDatabase,
            );
        }

        foreach ($blockedDatabases as $blocked) {
            if ($blocked !== '' && strtolower($blocked) === $normalizedDatabase) {
                throw new UnsafeTestDatabaseException(
                    'blocked_database_name',
                    sprintf(
                        'Unsafe test database configuration: resolved database "%s" is blocked for PHPUnit. Configure DB_DATABASE=%s in phpunit.xml and .env.testing. No database operation was performed.',
                        $database,
                        $approvedDatabase,
                    ),
                    $database,
                    $approvedDatabase,
                );
            }
        }

        if (strtolower($approvedDatabase) !== $normalizedDatabase) {
            throw new UnsafeTestDatabaseException(
                'database_not_approved',
                sprintf(
                    'Unsafe test database configuration: resolved database "%s" is not the approved disposable test database "%s". No database operation was performed.',
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
        if (! $app->environment('testing')) {
            return;
        }

        $config = $app->make('config');

        $connection = (string) $config->get('database.default', '');
        $database = (string) $config->get('database.connections.'.$connection.'.database', '');

        self::assertSafe(
            appEnv: (string) $app->environment(),
            connection: $connection,
            database: $database,
            approvedDatabase: (string) $config->get('testing.approved_database', 'cmart_test'),
            developmentDatabase: (string) $config->get('testing.development_database', 'cmart_db'),
            blockedDatabases: array_map('strval', $config->get('testing.blocked_databases', [])),
            approvedConnections: array_map('strval', $config->get('testing.approved_connections', ['mysql'])),
        );
    }
}
