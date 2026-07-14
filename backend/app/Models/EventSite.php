<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSite extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_DISABLED = 'disabled';

    public const OPERATIONAL_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_UNAVAILABLE,
        self::STATUS_DISABLED,
    ];

    protected $fillable = [
        'carboot_event_id',
        'space_id',
        'label',
        'row_label',
        'position_number',
        'grid_row',
        'grid_column',
        'display_order',
        'operational_status',
        'metadata',
    ];

    protected $casts = [
        'position_number' => 'integer',
        'grid_row' => 'integer',
        'grid_column' => 'integer',
        'display_order' => 'integer',
        'metadata' => 'array',
    ];

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
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

    public function scopeOrderedForLayout($query)
    {
        return $query
            ->orderBy('display_order')
            ->orderBy('grid_row')
            ->orderBy('grid_column')
            ->orderBy('row_label')
            ->orderBy('position_number');
    }

    public function isOperationallyActive(): bool
    {
        return $this->operational_status === self::STATUS_ACTIVE;
    }
}
