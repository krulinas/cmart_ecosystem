<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingAttendanceException extends Model
{
    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_SUBMITTED = 'payment_submitted';

    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'booking_id',
        'applied_by',
        'applied_by_name',
        'reason',
        'payment_state',
        'no_refund_acknowledged',
        'previous_retained_day_count',
        'retained_day_count',
        'released_day_count',
        'applied_at',
    ];

    protected $casts = [
        'no_refund_acknowledged' => 'boolean',
        'previous_retained_day_count' => 'integer',
        'retained_day_count' => 'integer',
        'released_day_count' => 'integer',
        'applied_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function days(): HasMany
    {
        return $this->hasMany(BookingAttendanceExceptionDay::class);
    }
}
