<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventDay extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DISABLED = 'disabled';

    public const OPERATIONAL_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CANCELLED,
        self::STATUS_DISABLED,
    ];

    protected $fillable = [
        'carboot_event_id',
        'operational_date',
        'starts_at',
        'ends_at',
        'operational_status',
        'display_order',
    ];

    protected $casts = [
        'operational_date' => 'date',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'display_order' => 'integer',
    ];

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function bookingDayAllocations(): HasMany
    {
        return $this->hasMany(BookingDayAllocation::class);
    }

    public function hasAllocationHistory(): bool
    {
        return $this->bookingDayAllocations()->exists();
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('carboot_event_id', $eventId);
    }

    public function scopeActive($query)
    {
        return $query->where('operational_status', self::STATUS_ACTIVE);
    }

    public function scopeOrdered($query)
    {
        return $query
            ->orderBy('display_order')
            ->orderBy('operational_date')
            ->orderBy('id');
    }

    public function isOperationallyActive(): bool
    {
        return $this->operational_status === self::STATUS_ACTIVE;
    }
}
