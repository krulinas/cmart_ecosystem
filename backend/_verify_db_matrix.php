<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$db = DB::connection()->getDatabaseName();
echo "active_db={$db}\n";
echo "reachable=1\n";

$core = [
    'users', 'carboot_events', 'bookings', 'vendor_items', 'item_reservations',
    'migrations', 'booking_audit_logs', 'vendor_item_event_selections', 'vendor_item_sales',
];

echo "\n== physical tables ==\n";
foreach ($core as $table) {
    $exists = Schema::hasTable($table) ? '1' : '0';
    echo "table={$table} exists={$exists}";
    if ($exists === '1') {
        try {
            $count = DB::table($table)->count();
            echo " count={$count}";
        } catch (Throwable $e) {
            echo ' count_error='.$e->getMessage();
        }
    }
    echo "\n";
}

echo "\n== migration records of interest ==\n";
$patterns = [
    'booking_audit',
    'vendor_item_event',
    'vendor_item_sales',
    '2026_09_21_100001',
    '2026_09_21_100002',
    '2026_06_03_000001',
];

$rows = DB::table('migrations')->orderBy('id')->get(['migration', 'batch']);
foreach ($rows as $row) {
    foreach ($patterns as $pattern) {
        if (str_contains($row->migration, $pattern)) {
            echo "migration={$row->migration} batch={$row->batch}\n";
            break;
        }
    }
}

echo "\n== matrix ==\n";
$matrix = [
    ['booking_audit_logs', '2026_06_03_000001_create_booking_audit_logs_table'],
    ['vendor_item_event_selections', '2026_09_21_100001_create_vendor_item_event_listings_table'],
    ['vendor_item_sales', '2026_09_21_100002_create_vendor_item_sales_table'],
];
foreach ($matrix as [$table, $migration]) {
    $phys = Schema::hasTable($table) ? 'present' : 'absent';
    $rec = DB::table('migrations')->where('migration', $migration)->exists() ? 'recorded' : 'unrecorded';
    echo "{$table}: physical={$phys} migration={$rec}\n";
}
