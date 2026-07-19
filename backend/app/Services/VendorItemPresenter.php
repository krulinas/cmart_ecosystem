<?php

namespace App\Services;

use App\Models\VendorItem;
use Illuminate\Support\Facades\Schema;

class VendorItemPresenter
{
    public static function fromModel(VendorItem $item): array
    {
        if (Schema::hasTable('reuse_item_images')) {
            $item->loadMissing('images');
        }

        $images = $item->galleryImagesForApi();
        $primaryPath = $item->primaryImagePath();
        $booking = MarketplaceEligibility::upcomingApprovedBookingForItem($item);
        $hasActiveReservation = array_key_exists('has_active_reservation', $item->getAttributes())
            ? (bool) $item->getAttribute('has_active_reservation')
            : $item->reservations()->active()->exists();

        return [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'name' => $item->name,
            'vendor_category_id' => $item->vendor_category_id !== null
                ? (int) $item->vendor_category_id
                : null,
            'category' => $item->category,
            'condition' => $item->condition,
            'pricing_type' => $item->pricing_type,
            'price' => $item->price !== null ? round((float) $item->price, 2) : null,
            'description' => $item->description,
            'status' => $item->status,
            'image_path' => $primaryPath,
            'image_url' => $primaryPath ? asset('storage/'.$primaryPath) : null,
            'images' => is_array($images) ? $images : [],
            'is_reservable' => $item->status === 'active'
                && $booking?->carbootEvent?->item_reservation_service_fee !== null
                && ! $hasActiveReservation,
            'has_active_reservation' => $hasActiveReservation,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
