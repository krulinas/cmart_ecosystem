<?php

/**
 * Phase 3.4 — read-only development database baseline (expects cmart_db).
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$connection = config('database.default');
$database = (string) config("database.connections.{$connection}.database");

echo 'APP_ENV=' . app()->environment() . PHP_EOL;
echo 'DB_CONNECTION=' . $connection . PHP_EOL;
echo 'DB_DATABASE=' . $database . PHP_EOL;

if ($database === 'cmart_test') {
    fwrite(STDERR, "Refusing: this script is for development baseline (cmart_db), not cmart_test.\n");
    fwrite(STDERR, "Unset APP_ENV=testing / DB_DATABASE overrides and rerun.\n");
    exit(2);
}

$tables = [
    'users',
    'carboot_events',
    'spaces',
    'event_sites',
    'event_days',
    'bookings',
    'booking_day_allocations',
    'invoices',
    'booking_audit_logs',
    'vendor_business_profiles',
    'vendor_items',
    'user_booking_preferences',
    'vendor_categories',
    'event_layout_rows',
    'category_migration_audits',
];

foreach ($tables as $table) {
    if (! Schema::hasTable($table)) {
        echo "{$table}=MISSING\n";
        continue;
    }
    echo $table . '=' . DB::table($table)->count() . PHP_EOL;
}
