<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class BookingDayAllocation extends Model
{
    use HasFactory;

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_CONFIRMED,
        self::STATUS_RELEASED,
        self::STATUS_CANCELLED,
    ];

    /** Statuses that hold a physical site-day (active_lock = 1). */
    public const OCCUPYING_STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_CONFIRMED,
    ];

    protected $fillable = [
        'booking_id',
        'event_day_id',
        'event_site_id',
        'allocation_status',
        'reserved_at',
        'confirmed_at',
        'released_at',
        'released_by',
        'release_reason',
        'active_lock',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'released_at' => 'datetime',
        'active_lock' => 'integer',
    ];

    /**
     * Canonical status → active_lock mapping (ADR-012).
     * Occupying statuses use 1; historical statuses use null (never 0).
     */
    public static function activeLockForStatus(string $status): ?int
    {
        return match ($status) {
            self::STATUS_RESERVED, self::STATUS_CONFIRMED => 1,
            self::STATUS_RELEASED, self::STATUS_CANCELLED => null,
            default => throw new InvalidArgumentException(
                "Unknown allocation_status [{$status}]."
            ),
        };
    }

    public static function allowedStatuses(): array
    {
        return self::STATUSES;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }

    public function eventSite(): BelongsTo
    {
        return $this->belongsTo(EventSite::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function scopeForBooking($query, int $bookingId)
    {
        return $query->where('booking_id', $bookingId);
    }

    public function scopeActiveOccupancy($query)
    {
        return $query->where('active_lock', 1);
    }

    public function scopeReserved($query)
    {
        return $query->where('allocation_status', self::STATUS_RESERVED);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('allocation_status', self::STATUS_CONFIRMED);
    }

    public function scopeHistorical($query)
    {
        return $query->whereNull('active_lock');
    }

    public function occupiesSite(): bool
    {
        return $this->active_lock === 1;
    }

    protected static function booted(): void
    {
        static::saving(function (self $allocation) {
            $expected = self::activeLockForStatus($allocation->allocation_status);
            $actual = $allocation->active_lock;

            // Normalize empty string / accidental 0 away from occupying path.
            if ($actual === 0) {
                $actual = null;
                $allocation->active_lock = null;
            }

            if ($expected !== $actual) {
                $allocation->active_lock = $expected;
            }
        });
    }
}
