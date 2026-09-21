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
        $hasActiveReservation = $item->hasActiveReservationFlag();
        $hasSale = $item->hasSale();
        $displayStatus = $item->displayStatus();

        $selectedEventIds = [];
        if (Schema::hasTable('vendor_item_event_selections')) {
            $item->loadMissing(['eventListings.carbootEvent']);
            $selectedEventIds = $item->eventListings
                ->filter(fn ($listing) => $listing->carbootEvent
                    && $listing->carbootEvent->ends_at >= now())
                ->pluck('carboot_event_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

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
            'visibility' => $item->status === 'active' ? 'visible' : 'hidden',
            'display_status' => $displayStatus,
            'image_path' => $primaryPath,
            'image_url' => $primaryPath ? asset('storage/'.$primaryPath) : null,
            'images' => is_array($images) ? $images : [],
            'is_reservable' => $displayStatus === 'available' && $selectedEventIds !== [],
            'has_active_reservation' => $hasActiveReservation,
            'has_sale' => $hasSale,
            'selected_event_ids' => $selectedEventIds,
            'can_delete' => ! $hasSale && ! $item->reservations()->exists(),
            'can_mark_sold_walk_in' => $displayStatus === 'available' && $selectedEventIds !== [],
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
