<?php

/**
 * Phase 3.3 — read-only table counts for the resolved testing database.
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! $app->environment('testing')) {
    fwrite(STDERR, "Refusing to run: APP_ENV must be testing.\n");
    exit(1);
}

$database = (string) config('database.connections.' . config('database.default') . '.database');
if ($database !== (string) config('testing.approved_database', 'cmart_test')) {
    fwrite(STDERR, "Refusing to run: resolved database is not the approved test database.\n");
    exit(1);
}

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorBusinessProfile;

$tables = [
    'users' => User::count(),
    'carboot_events' => CarbootEvent::count(),
    'spaces' => Space::count(),
    'event_sites' => EventSite::count(),
    'event_days' => EventDay::count(),
    'bookings' => Booking::count(),
    'booking_day_allocations' => BookingDayAllocation::count(),
    'invoices' => Invoice::count(),
    'booking_audit_logs' => BookingAuditLog::count(),
    'vendor_business_profiles' => VendorBusinessProfile::count(),
];

echo "DB_DATABASE={$database}\n";
foreach ($tables as $name => $count) {
    echo "{$name}={$count}\n";
}
