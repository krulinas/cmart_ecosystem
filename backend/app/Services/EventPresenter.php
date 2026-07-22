<?php

namespace App\Services;

use App\Models\CarbootEvent;

class EventPresenter
{
    public static function fromModel(CarbootEvent $event, bool $includeOrganizerConfiguration = false): array
    {
        $event->loadMissing('images');

        $images = $event->galleryImagesForApi();
        $primaryPath = $event->primaryImagePath();
        $imageUrl = $primaryPath ? asset('storage/'.$primaryPath) : null;

        $payload = array_merge($event->only([
            'id',
            'title',
            'starts_at',
            'ends_at',
            'status',
            'description',
            'max_slots',
            'day_generation_mode',
            'image_path',
            'created_at',
            'updated_at',
        ]), [
            'poster_url' => $imageUrl,
            'image_url' => $imageUrl,
            'images' => $images,
        ]);

        if ($includeOrganizerConfiguration) {
            $payload['item_reservation_service_fee'] = $event->item_reservation_service_fee;
            $payload['site_price'] = $event->site_price !== null
                ? number_format((float) $event->site_price, 2, '.', '')
                : null;
            $payload['has_bookings'] = $event->bookings()->exists();
        }

        return $payload;
    }
}
