<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class ItemReservation extends Model
{
    use HasFactory;

    public const STATUS_PENDING_CHARGE = 'pending_charge';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING_CHARGE,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
        self::STATUS_COMPLETED,
    ];

    public const CHARGE_REQUIRED = 'required';

    public const CHARGE_CONFIRMED = 'confirmed';

    public const CHARGE_WAIVED = 'waived';

    public const CHARGE_NOT_REQUIRED = 'not_required';

    public const CHARGE_CANCELLED = 'cancelled';

    public const CHARGE_STATUSES = [
        self::CHARGE_REQUIRED,
        self::CHARGE_CONFIRMED,
        self::CHARGE_WAIVED,
        self::CHARGE_NOT_REQUIRED,
        self::CHARGE_CANCELLED,
    ];

    protected $fillable = [
        'public_reference',
        'vendor_item_id',
        'reserving_user_id',
        'vendor_user_id',
        'carboot_event_id',
        'vendor_booking_id',
        'reservation_status',
        'active_lock',
        'service_fee_amount',
        'service_fee_currency',
        'charge_status',
        'charge_confirmation_note',
        'charge_confirmed_by',
        'charge_confirmed_at',
        'charge_waive_reason',
        'charge_waived_by',
        'charge_waived_at',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'expired_by',
        'expired_at',
        'completed_by',
        'completed_at',
        'item_name_snapshot',
    ];

    protected $casts = [
        'active_lock' => 'integer',
        'service_fee_amount' => 'decimal:2',
        'charge_confirmed_at' => 'datetime',
        'charge_waived_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function activeLockForStatus(string $status): ?int
    {
        return match ($status) {
            self::STATUS_PENDING_CHARGE, self::STATUS_CONFIRMED => 1,
            self::STATUS_CANCELLED, self::STATUS_EXPIRED, self::STATUS_COMPLETED => null,
            default => throw new InvalidArgumentException(
                "Unknown reservation_status [{$status}].",
            ),
        };
    }

    public function vendorItem(): BelongsTo
    {
        return $this->belongsTo(VendorItem::class);
    }

    public function reservingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserving_user_id');
    }

    public function vendorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class);
    }

    public function vendorBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'vendor_booking_id');
    }

    public function chargeConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'charge_confirmed_by');
    }

    public function chargeWaiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'charge_waived_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function expiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expired_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ItemReservationAudit::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('active_lock', 1);
    }

    public function getRouteKeyName(): string
    {
        return 'public_reference';
    }

    protected static function booted(): void
    {
        static::saving(function (self $reservation) {
            $reservation->active_lock = self::activeLockForStatus(
                $reservation->reservation_status,
            );
        });
    }
}
