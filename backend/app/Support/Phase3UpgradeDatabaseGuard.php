<?php

namespace App\Support;

use App\Exceptions\UnsafeTestDatabaseException;

class Phase3UpgradeDatabaseGuard
{
    public const APPROVED_DATABASE = 'cmart_phase3_upgrade_test';

    public static function assertSafe(string $driver, string $database): void
    {
        if ($driver !== 'mysql') {
            throw new UnsafeTestDatabaseException(
                'connection_not_approved',
                'Unsafe Phase 3 upgrade simulation: only mysql is approved. No database operation was performed.',
                $database !== '' ? $database : null,
                self::APPROVED_DATABASE,
            );
        }

        if (strtolower(trim($database)) !== self::APPROVED_DATABASE) {
            throw new UnsafeTestDatabaseException(
                'database_not_approved',
                sprintf(
                    'Unsafe Phase 3 upgrade simulation: database "%s" is not the approved disposable database "%s". No database operation was performed.',
                    $database === '' ? '(empty)' : $database,
                    self::APPROVED_DATABASE,
                ),
                $database !== '' ? $database : null,
                self::APPROVED_DATABASE,
            );
        }
    }
}
