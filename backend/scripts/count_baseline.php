<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Feedback;
use App\Models\Invoice;
use App\Models\NewsPost;
use App\Models\Space;
use App\Models\User;

$tables = [
    'users' => User::count(),
    'carboot_events' => CarbootEvent::count(),
    'event_sites' => EventSite::count(),
    'event_days' => EventDay::count(),
    'spaces' => Space::count(),
    'bookings' => Booking::count(),
    'invoices' => Invoice::count(),
    'booking_day_allocations' => BookingDayAllocation::count(),
    'booking_audit_logs' => BookingAuditLog::count(),
    'news' => NewsPost::count(),
    'feedback' => Feedback::count(),
];

foreach ($tables as $name => $count) {
    echo "{$name}={$count}\n";
}

echo "\n--- test-pattern users ---\n";
foreach (User::query()
    ->where(function ($q) {
        $q->where('email', 'like', '%@example.com')
            ->orWhereIn('email', ['venue@cmart.com', 'organizer@cmart.com', 'admin@cmart.com']);
    })
    ->orderBy('id')
    ->get(['id', 'email', 'role']) as $u) {
    echo "user#{$u->id} email={$u->email} role={$u->role}\n";
}

echo "\n--- test-pattern events ---\n";
foreach (CarbootEvent::query()
    ->where(function ($q) {
        $q->where('title', 'like', '%Test Event%')
            ->orWhere('title', 'like', '%Allocation Event%')
            ->orWhere('title', 'like', '%Creation Test%')
            ->orWhere('title', 'like', '%Lifecycle Event%')
            ->orWhere('title', 'like', '%Workflow Test%')
            ->orWhere('description', 'like', '%Phase 2A%')
            ->orWhere('description', 'like', '%test%');
    })
    ->orderBy('id')
    ->get(['id', 'title']) as $e) {
    echo "event#{$e->id} title={$e->title}\n";
}

echo "\nDB_CONNECTION=" . config('database.default') . "\n";
echo "DB_DATABASE=" . config('database.connections.' . config('database.default') . '.database') . "\n";
