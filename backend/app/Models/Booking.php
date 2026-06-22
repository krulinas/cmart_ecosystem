<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'space_id',
        'carboot_event_id',
        'booking_date',
        'product_category',
        'product_details',
        'approval_status',
        'checked_in_at',
        'revision_comment',
        'vendor_request_type',
        'vendor_request_note',
        'withdrawn_at',
        'withdrawal_reason',
        'withdrawn_by',
        'whatsapp_link',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'checked_in_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    /** Exclude invalid legacy/test dates from vendor-facing lists. */
    public function scopeWithValidBookingDate($query)
    {
        return $query->where('booking_date', '>', '1970-01-01');
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function space() {
        return $this->belongsTo(Space::class);
    }

    public function carbootEvent() {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function invoice() {
        return $this->hasOne(Invoice::class);
    }

    public function auditLogs() {
        return $this->hasMany(BookingAuditLog::class);
    }

    public function withdrawnBy() {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }
}