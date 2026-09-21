<?php

namespace App\Services;

use App\Models\CarbootEvent;
use App\Models\VendorBusinessProfile;
use App\Models\VendorItem;
use App\Support\WhatsAppContact;

class MarketplaceItemPresenter
{
    public static function fromItem(
        VendorItem $item,
        bool $detailed = false,
        ?int $viewerUserId = null,
    ): array {
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

        $feeConfigured = $event?->item_reservation_service_fee !== null;
        $isOwnItem = $viewerUserId !== null && (int) $viewerUserId === (int) $item->user_id;

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
            'is_reservable' => $feeConfigured && ! $hasActiveReservation,
            'has_active_reservation' => $hasActiveReservation,
            'is_own_item' => $isOwnItem,
            'reservation_service_fee' => $feeConfigured
                ? round((float) $event->item_reservation_service_fee, 2)
                : null,
            'reservation_service_fee_currency' => $feeConfigured ? 'MYR' : null,
            'reservation_availability' => self::reservationAvailability(
                $event,
                $hasActiveReservation,
                $isOwnItem,
            ),
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

            $contact = self::whatsappContact($profile, $item, $event);
            if ($contact) {
                $payload['vendor']['whatsapp_contact'] = $contact;
            }
        }

        return $payload;
    }

    private static function reservationAvailability(
        ?CarbootEvent $event,
        bool $hasActiveReservation,
        bool $isOwnItem,
    ): array {
        if ($isOwnItem) {
            return [
                'available' => false,
                'code' => 'own_item',
                'message' => __('api.this_is_your_listing'),
            ];
        }

        if ($event === null) {
            return [
                'available' => false,
                'code' => 'no_eligible_upcoming_event',
                'message' => __('api.reservations_are_not_available_because_this_vendor_750ef807'),
            ];
        }

        if ($event->item_reservation_service_fee === null) {
            return [
                'available' => false,
                'code' => 'event_reservations_not_configured',
                'message' => __('api.reservations_are_not_available_for_this_event'),
            ];
        }

        if ($hasActiveReservation) {
            return [
                'available' => false,
                'code' => 'already_reserved',
                'message' => __('api.this_item_already_has_an_active_reservation'),
            ];
        }

        return [
            'available' => true,
            'code' => 'available',
            'message' => __('api.this_item_can_be_reserved_as_a_temporary_hold'),
        ];
    }

    private static function whatsappContact(
        ?VendorBusinessProfile $profile,
        VendorItem $item,
        ?CarbootEvent $event,
    ): ?array {
        $vendorName = $profile?->business_name ?: ($item->user?->name ?? 'CMart Vendor');
        $eventLabel = $event?->starts_at?->format('j M Y') ?: $event?->title;

        return WhatsAppContact::publicContact(
            $profile,
            WhatsAppContact::marketplaceMessage($vendorName, (string) $item->name, $eventLabel),
        );
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
