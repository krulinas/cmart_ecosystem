<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = [
    'users',
    'vendor_business_profiles',
    'carboot_events',
    'event_days',
    'vendor_categories',
    'event_layout_rows',
    'event_sites',
    'bookings',
    'booking_day_allocations',
    'invoices',
    'booking_audit_logs',
    'event_layout_audit_logs',
    'booking_category_overrides',
    'spaces',
];

echo 'database=' . DB::connection()->getDatabaseName() . PHP_EOL;
foreach ($tables as $table) {
    echo $table . '=' . (Schema::hasTable($table) ? DB::table($table)->count() : 'MISSING') . PHP_EOL;
}
