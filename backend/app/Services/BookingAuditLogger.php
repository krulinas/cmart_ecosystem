<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class BookingAuditLogger
{
    public static function log(
        Booking $booking,
        User $actor,
        string $fromStatus,
        string $toStatus,
        ?string $revisionComment = null,
        ?Request $request = null,
    ): BookingAuditLog {
        return BookingAuditLog::create([
            'booking_id' => $booking->id,
            'actor_user_id' => $actor->id,
            'action' => 'status_change',
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'revision_comment' => $revisionComment,
            'ip_address' => $request?->ip(),
        ]);
    }
}
