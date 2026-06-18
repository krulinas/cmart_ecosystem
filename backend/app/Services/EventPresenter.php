<?php

namespace App\Services;

use App\Models\CarbootEvent;

class EventPresenter
{
    public static function fromModel(CarbootEvent $event): array
    {
        $event->loadMissing('images');

        $images = $event->galleryImagesForApi();
        $primaryPath = $event->primaryImagePath();
        $imageUrl = $primaryPath ? asset('storage/' . $primaryPath) : null;

        return array_merge($event->only([
            'id',
            'title',
            'starts_at',
            'ends_at',
            'status',
            'description',
            'max_slots',
            'image_path',
            'created_at',
            'updated_at',
        ]), [
            'poster_url' => $imageUrl,
            'image_url' => $imageUrl,
            'images' => $images,
        ]);
    }
}
