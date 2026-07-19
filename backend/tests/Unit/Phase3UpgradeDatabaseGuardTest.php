<?php

namespace Tests\Unit;

use App\Exceptions\UnsafeTestDatabaseException;
use App\Support\Phase3UpgradeDatabaseGuard;
use PHPUnit\Framework\TestCase;

class Phase3UpgradeDatabaseGuardTest extends TestCase
{
    public function test_only_dedicated_mysql_upgrade_database_is_allowed(): void
    {
        Phase3UpgradeDatabaseGuard::assertSafe('mysql', 'cmart_phase3_upgrade_test');
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider unsafeConfigurations
     */
    public function test_protected_or_unapproved_databases_are_rejected(
        string $driver,
        string $database,
    ): void {
        $this->expectException(UnsafeTestDatabaseException::class);
        Phase3UpgradeDatabaseGuard::assertSafe($driver, $database);
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'development' => ['mysql', 'cmart_db'],
            'phpunit' => ['mysql', 'cmart_test'],
            'e2e' => ['mysql', 'cmart_e2e_db'],
            'production alias' => ['mysql', 'cmart'],
            'empty' => ['mysql', ''],
            'sqlite' => ['sqlite', 'cmart_phase3_upgrade_test'],
        ];
    }
}
