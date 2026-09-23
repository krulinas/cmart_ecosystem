<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorItemEventListing extends Model
{
    protected $table = 'vendor_item_event_selections';

    protected $fillable = [
        'vendor_item_id',
        'carboot_event_id',
        'vendor_booking_id',
        'vendor_user_id',
        'selected_by',
        'selected_at',
    ];

    protected $casts = [
        'selected_at' => 'datetime',
    ];

    public function vendorItem(): BelongsTo
    {
        return $this->belongsTo(VendorItem::class);
    }

    public function carbootEvent(): BelongsTo
    {
        return $this->belongsTo(CarbootEvent::class);
    }

    public function vendorBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'vendor_booking_id');
    }

    public function vendorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }
}
