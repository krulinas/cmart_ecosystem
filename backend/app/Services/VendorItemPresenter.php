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

        return [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'name' => $item->name,
            'category' => $item->category,
            'condition' => $item->condition,
            'pricing_type' => $item->pricing_type,
            'price' => $item->price !== null ? round((float) $item->price, 2) : null,
            'description' => $item->description,
            'status' => $item->status,
            'image_path' => $primaryPath,
            'image_url' => $primaryPath ? asset('storage/' . $primaryPath) : null,
            'images' => is_array($images) ? $images : [],
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
