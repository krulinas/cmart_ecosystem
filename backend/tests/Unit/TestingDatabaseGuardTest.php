<?php

namespace Tests\Unit;

use App\Exceptions\UnsafeTestDatabaseException;
use App\Support\TestingDatabaseGuard;
use PHPUnit\Framework\TestCase;

class TestingDatabaseGuardTest extends TestCase
{
    private const APPROVED = 'cmart_test';

    private const DEVELOPMENT = 'cmart_db';

    /** @var list<string> */
    private array $blocked = [
        'cmart_db',
        'cmart',
        'production',
        'prod',
        'staging',
        'main',
        'development',
        'dev',
    ];

    public function test_approved_test_database_is_accepted(): void
    {
        TestingDatabaseGuard::assertSafe(
            appEnv: 'testing',
            connection: 'mysql',
            database: self::APPROVED,
            approvedDatabase: self::APPROVED,
            developmentDatabase: self::DEVELOPMENT,
            blockedDatabases: $this->blocked,
        );

        $this->addToAssertionCount(1);
    }

    public function test_cmart_db_is_rejected(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage('cmart_db');
        $this->expectExceptionMessage('No database operation was performed');

        TestingDatabaseGuard::assertSafe(
            appEnv: 'testing',
            connection: 'mysql',
            database: 'cmart_db',
            approvedDatabase: self::APPROVED,
            developmentDatabase: self::DEVELOPMENT,
            blockedDatabases: $this->blocked,
        );
    }

    public function test_empty_database_name_is_rejected(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage('empty value');

        TestingDatabaseGuard::assertSafe(
            appEnv: 'testing',
            connection: 'mysql',
            database: '',
            approvedDatabase: self::APPROVED,
            developmentDatabase: self::DEVELOPMENT,
            blockedDatabases: $this->blocked,
        );
    }

    public function test_development_like_database_name_is_rejected(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage('development');

        TestingDatabaseGuard::assertSafe(
            appEnv: 'testing',
            connection: 'mysql',
            database: 'development',
            approvedDatabase: self::APPROVED,
            developmentDatabase: self::DEVELOPMENT,
            blockedDatabases: $this->blocked,
        );
    }

    public function test_production_like_database_name_is_rejected(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage('production');

        TestingDatabaseGuard::assertSafe(
            appEnv: 'testing',
            connection: 'mysql',
            database: 'production',
            approvedDatabase: self::APPROVED,
            developmentDatabase: self::DEVELOPMENT,
            blockedDatabases: $this->blocked,
        );
    }

    public function test_non_testing_environment_is_rejected(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage('APP_ENV must be "testing"');

        TestingDatabaseGuard::assertSafe(
            appEnv: 'local',
            connection: 'mysql',
            database: self::APPROVED,
            approvedDatabase: self::APPROVED,
            developmentDatabase: self::DEVELOPMENT,
            blockedDatabases: $this->blocked,
        );
    }

    public function test_guard_error_does_not_include_password(): void
    {
        try {
            TestingDatabaseGuard::assertSafe(
                appEnv: 'testing',
                connection: 'mysql',
                database: 'cmart_db',
                approvedDatabase: self::APPROVED,
                developmentDatabase: self::DEVELOPMENT,
                blockedDatabases: $this->blocked,
            );
            $this->fail('Expected UnsafeTestDatabaseException was not thrown.');
        } catch (UnsafeTestDatabaseException $exception) {
            $this->assertStringNotContainsString('password', strtolower($exception->getMessage()));
            $this->assertContains($exception->reasonCode, ['development_database_resolved', 'blocked_database_name']);
            $this->assertSame('cmart_db', $exception->resolvedDatabase);
        }
    }

    public function test_unapproved_but_non_blocked_database_is_rejected(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage('not the approved disposable test database');

        TestingDatabaseGuard::assertSafe(
            appEnv: 'testing',
            connection: 'mysql',
            database: 'cmart_test_copy',
            approvedDatabase: self::APPROVED,
            developmentDatabase: self::DEVELOPMENT,
            blockedDatabases: $this->blocked,
        );
    }
}
