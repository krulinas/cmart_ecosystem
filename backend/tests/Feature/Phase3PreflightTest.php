<?php

namespace Tests\Feature;

use App\Services\Phase3PreflightService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase3PreflightTest extends TestCase
{
    private const TABLES = [
        'vendor_categories',
        'category_migration_audits',
        'event_layout_rows',
        'event_sites',
        'bookings',
        'booking_day_allocations',
        'booking_category_overrides',
    ];

    public function test_preflight_reports_database_migrations_trigger_and_integrity_without_mutation(): void
    {
        $before = $this->counts();

        $result = app(Phase3PreflightService::class)->inspect();

        $this->assertSame('testing', $result['environment']);
        $this->assertSame('cmart_test', $result['database']);
        $this->assertSame('mysql', $result['driver']);
        $this->assertTrue($result['read_only']);
        $this->assertTrue($result['schema_ready']);
        $this->assertSame(0, $result['migrations']['pending_count']);
        $this->assertSame(7, $result['canonical_category_count']);
        $this->assertTrue($result['trigger']['present']);
        $this->assertTrue($result['trigger']['definition_verified']);
        $this->assertIsBool($result['trigger']['create_trigger_privilege']);
        $this->assertSame($before, $this->counts());
    }

    public function test_preflight_command_emits_json_and_succeeds(): void
    {
        $this->artisan('phase3:preflight', ['--json' => true])
            ->expectsOutputToContain('"read_only": true')
            ->assertExitCode(0);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return collect(self::TABLES)
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
            ->all();
    }
}
