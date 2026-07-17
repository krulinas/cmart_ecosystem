<?php

namespace Tests\Unit;

use App\Exceptions\UnsafeTestDatabaseException;
use App\Support\E2EDatabaseGuard;
use PHPUnit\Framework\TestCase;

class E2EDatabaseGuardTest extends TestCase
{
    public function test_exact_isolated_database_is_accepted(): void
    {
        E2EDatabaseGuard::assertSafe('e2e', 'mysql', 'cmart_e2e_db');
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider unsafeConfigurations
     */
    public function test_unsafe_configuration_is_rejected(
        string $environment,
        string $connection,
        string $database,
    ): void {
        $this->expectException(UnsafeTestDatabaseException::class);
        E2EDatabaseGuard::assertSafe($environment, $connection, $database);
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'development database' => ['e2e', 'mysql', 'cmart_db'],
            'legacy database' => ['e2e', 'mysql', 'cmart'],
            'unknown database' => ['e2e', 'mysql', 'other_test_db'],
            'production-like database' => ['e2e', 'mysql', 'production'],
            'wrong environment' => ['local', 'mysql', 'cmart_e2e_db'],
            'wrong connection' => ['e2e', 'sqlite', 'cmart_e2e_db'],
            'empty database' => ['e2e', 'mysql', ''],
        ];
    }
}
