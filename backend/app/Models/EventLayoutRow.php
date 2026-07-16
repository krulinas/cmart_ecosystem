<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventLayoutRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'carboot_event_id',
        'vendor_category_id',
        'label',
        'slug',
        'description',
        'display_order',
        'is_active',
        'is_public',
        'created_by',
        'updated_by',
        'archived_at',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class, 'carboot_event_id');
    }

    public function vendorCategory(): BelongsTo
    {
        return $this->belongsTo(VendorCategory::class);
    }

    public function eventSites(): HasMany
    {
        return $this->hasMany(EventSite::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('id');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('carboot_event_id', $eventId);
    }
}
