<?php

namespace App\Services;

use App\Models\VendorBusinessProfile;
use App\Models\VendorItem;

class MarketplaceItemPresenter
{
    public static function fromItem(VendorItem $item, bool $detailed = false): array
    {
        $item->loadMissing(['user.businessProfile', 'images']);
        $profile = $item->user?->businessProfile;
        $vendor = self::publicVendorSummary($profile, $item);
        $images = $item->galleryImagesForApi();
        $primaryPath = $item->primaryImagePath();
        $booking = MarketplaceEligibility::upcomingApprovedBookingForItem($item);
        $event = $booking?->carbootEvent;
        $hasActiveReservation = array_key_exists('has_active_reservation', $item->getAttributes())
            ? (bool) $item->getAttribute('has_active_reservation')
            : $item->reservations()->active()->exists();

        $payload = [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'condition' => $item->condition,
            'pricing_type' => $item->pricing_type,
            'price' => $item->pricing_type === 'fixed' ? round((float) $item->price, 2) : null,
            'description' => $detailed
                ? $item->description
                : self::truncate($item->description, 140),
            'image_path' => $primaryPath,
            'image_url' => $primaryPath ? asset('storage/'.$primaryPath) : null,
            'images' => $images,
            'listed_at' => $item->created_at?->toIso8601String(),
            'vendor' => $vendor,
            'purchase_mode' => 'in-person only',
            'is_reservable' => $event?->item_reservation_service_fee !== null
                && ! $hasActiveReservation,
            'has_active_reservation' => $hasActiveReservation,
            'event' => $event ? [
                'title' => $event->title,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'date_label' => $event->starts_at?->format('j M Y'),
            ] : null,
        ];

        if ($detailed) {
            $payload['vendor'] = [
                ...$vendor,
                'description' => $profile?->description,
            ];
        }

        return $payload;
    }

    private static function publicVendorSummary(?VendorBusinessProfile $profile, VendorItem $item): array
    {
        $fallbackName = $item->user?->name ?? 'CMart Vendor';

        return [
            'business_name' => $profile?->business_name ?: $fallbackName,
            'business_category' => $profile?->business_category,
            'logo_url' => $profile?->logo_url,
        ];
    }

    private static function truncate(?string $value, int $limit): ?string
    {
        if (! $value) {
            return null;
        }

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1)).'…';
    }
}
