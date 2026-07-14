<?php

/**
 * One-time removal of confirmed PHPUnit residue (Phase 2A.7.1).
 * Only deletes users provisioned by tests without teardown — not seeded accounts.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$residueEmails = [
    'organizer@cmart.com',
    'venue@cmart.com',
];

foreach ($residueEmails as $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        echo "skip: {$email} not found\n";
        continue;
    }

    if (Booking::where('user_id', $user->id)->exists()) {
        echo "abort: {$email} has bookings — not deleting\n";
        continue;
    }

    DB::table('personal_access_tokens')
        ->where('tokenable_type', User::class)
        ->where('tokenable_id', $user->id)
        ->delete();
    BookingAuditLog::where('actor_user_id', $user->id)->delete();
    $user->delete();

    echo "deleted user#{$user->id} ({$email})\n";
}
