<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestingDatabaseGuardBootTest extends TestCase
{
    public function test_phpunit_boot_path_resolves_approved_test_database_and_invokes_guard(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('cmart_test', config('database.connections.mysql.database'));

        DB::connection()->getPdo();

        $this->addToAssertionCount(1);
    }
}
