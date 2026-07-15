<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAttendanceExceptionDay extends Model
{
    public const DISPOSITION_RETAINED = 'retained';

    public const DISPOSITION_RELEASED = 'released';

    protected $fillable = [
        'booking_attendance_exception_id',
        'event_day_id',
        'disposition',
    ];

    public function exception(): BelongsTo
    {
        return $this->belongsTo(
            BookingAttendanceException::class,
            'booking_attendance_exception_id',
        );
    }

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }
}
