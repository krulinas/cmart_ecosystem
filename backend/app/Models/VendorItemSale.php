<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorItemSale extends Model
{
    public const SOURCE_RESERVED = 'reserved';
    public const SOURCE_WALK_IN = 'walk_in';

    protected $fillable = [
        'vendor_item_id',
        'vendor_user_id',
        'carboot_event_id',
        'vendor_booking_id',
        'item_reservation_id',
        'sale_source',
        'item_name_snapshot',
        'asking_price_snapshot',
        'final_sale_price',
        'currency',
        'sold_at',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'asking_price_snapshot' => 'decimal:2',
        'final_sale_price' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    public function vendorItem(): BelongsTo
    {
        return $this->belongsTo(VendorItem::class);
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

    public function itemReservation(): BelongsTo
    {
        return $this->belongsTo(ItemReservation::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
