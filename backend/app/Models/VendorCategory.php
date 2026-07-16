<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'label',
        'description',
        'display_order',
        'is_active',
        'is_public',
        'archived_at',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('id');
    }

    public function eventLayoutRows(): HasMany
    {
        return $this->hasMany(EventLayoutRow::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function vendorBusinessProfiles(): HasMany
    {
        return $this->hasMany(VendorBusinessProfile::class);
    }

    public function vendorItems(): HasMany
    {
        return $this->hasMany(VendorItem::class);
    }

    public function userBookingPreferences(): HasMany
    {
        return $this->hasMany(UserBookingPreference::class);
    }
}
