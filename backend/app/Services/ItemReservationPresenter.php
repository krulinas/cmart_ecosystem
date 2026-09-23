<?php

namespace App\Services;

use App\Models\ItemReservation;
use App\Models\ItemReservationAudit;
use App\Models\User;
use App\Support\WhatsAppContact;

class ItemReservationPresenter
{
    public static function forReservingUser(ItemReservation $reservation): array
    {
        $reservation->loadMissing(['carbootEvent', 'vendorUser.businessProfile']);

        $vendor = [
            'business_name' => $reservation->vendorUser?->businessProfile?->business_name
                ?: $reservation->vendorUser?->name,
        ];

        $contact = self::whatsappContactForReservingUser($reservation);
        if ($contact) {
            $vendor['whatsapp_contact'] = $contact;
        }

        return [
            ...self::common($reservation),
            'vendor' => $vendor,
        ];
    }

    public static function forVendor(ItemReservation $reservation): array
    {
        $reservation->loadMissing(['carbootEvent', 'reservingUser']);

        return [
            ...self::common($reservation),
            'reserving_user' => [
                'name' => $reservation->reservingUser?->name,
            ],
        ];
    }

    public static function forOrganizerQueue(ItemReservation $reservation): array
    {
        $reservation->loadMissing(['vendorUser.businessProfile', 'reservingUser']);

        return [
            'public_reference' => $reservation->public_reference,
            'reservation_status' => $reservation->reservation_status,
            'charge_status' => $reservation->charge_status,
            'service_fee_amount' => $reservation->service_fee_amount,
            'service_fee_currency' => $reservation->service_fee_currency,
            'item_name' => $reservation->item_name_snapshot,
            'vendor' => self::operationalIdentity($reservation->vendorUser, $reservation),
            'reserving_user' => self::operationalIdentity($reservation->reservingUser),
            'created_at' => $reservation->created_at?->toIso8601String(),
        ];
    }

    public static function forOrganizer(ItemReservation $reservation): array
    {
        $reservation->loadMissing([
            'carbootEvent',
            'vendorUser.businessProfile',
            'reservingUser',
            'chargeConfirmer',
            'chargeWaiver',
            'cancelledBy',
            'expiredBy',
            'completedBy',
        ]);

        return [
            ...self::common($reservation),
            'vendor' => self::operationalIdentity($reservation->vendorUser, $reservation),
            'reserving_user' => self::operationalIdentity($reservation->reservingUser),
            'charge_confirmation' => [
                'note' => $reservation->charge_confirmation_note,
                'confirmed_by' => $reservation->chargeConfirmer?->name,
                'confirmed_at' => $reservation->charge_confirmed_at?->toIso8601String(),
            ],
            'charge_waiver' => [
                'reason' => $reservation->charge_waive_reason,
                'waived_by' => $reservation->chargeWaiver?->name,
                'waived_at' => $reservation->charge_waived_at?->toIso8601String(),
            ],
            'cancelled_by' => $reservation->cancelledBy?->name,
            'expired_by' => $reservation->expiredBy?->name,
            'expired_at' => $reservation->expired_at?->toIso8601String(),
            'completed_by' => $reservation->completedBy?->name,
            'completed_at' => $reservation->completed_at?->toIso8601String(),
        ];
    }

    public static function auditEntry(ItemReservationAudit $audit): array
    {
        $audit->loadMissing('actorUser');

        return [
            'action' => $audit->action,
            'actor' => $audit->actorUser?->name,
            'from_reservation_status' => $audit->from_reservation_status,
            'to_reservation_status' => $audit->to_reservation_status,
            'from_charge_status' => $audit->from_charge_status,
            'to_charge_status' => $audit->to_charge_status,
            'note' => $audit->note,
            'metadata' => $audit->metadata,
            'created_at' => $audit->created_at?->toIso8601String(),
        ];
    }

    private static function operationalIdentity(?User $user, ?ItemReservation $withBusiness = null): array
    {
        return array_filter([
            'name' => $user?->name,
            'email' => $user?->email,
            'business_name' => $withBusiness?->vendorUser?->businessProfile?->business_name,
        ], fn ($value) => $value !== null);
    }

    private static function common(ItemReservation $reservation): array
    {
        $reservation->loadMissing([
            'carbootEvent',
            'vendorItem',
            'vendorBooking.bookingDayAllocations.eventSite',
        ]);

        $collectionSites = self::resolveCollectionSites($reservation);
        $locationAvailable = $collectionSites !== [];
        $siteCodes = array_column($collectionSites, 'code');
        $isConfirmed = $reservation->reservation_status === ItemReservation::STATUS_CONFIRMED;

        return [
            'public_reference' => $reservation->public_reference,
            'reservation_status' => $reservation->reservation_status,
            'charge_status' => $reservation->charge_status,
            'service_fee_amount' => $reservation->service_fee_amount,
            'service_fee_currency' => $reservation->service_fee_currency,
            'item' => [
                'id' => $reservation->vendor_item_id,
                'name' => $reservation->item_name_snapshot,
                'image_url' => $reservation->vendorItem?->image_url,
                'asking_price' => $reservation->vendorItem?->pricing_type === 'fixed'
                    ? round((float) $reservation->vendorItem->price, 2)
                    : null,
            ],
            'event' => [
                'id' => $reservation->carboot_event_id,
                'title' => $reservation->carbootEvent?->title,
                'starts_at' => $reservation->carbootEvent?->starts_at?->toIso8601String(),
                'ends_at' => $reservation->carbootEvent?->ends_at?->toIso8601String(),
                'venue' => 'CMart Kompleks Changlun',
            ],
            'collection' => [
                'collection_sites' => $collectionSites,
                'collection_location_available' => $locationAvailable,
                // Physical labels only — never invent from site_quantity / boothNumber fallback.
                'booth_number' => $locationAvailable ? implode(', ', $siteCodes) : null,
                'site_labels' => $siteCodes,
                'guidance' => $isConfirmed
                    ? __('api.collect_in_person_at_the_vendor_booth_during_the_event')
                    : null,
            ],
            'cancellation_reason' => $reservation->cancellation_reason,
            'created_at' => $reservation->created_at?->toIso8601String(),
            'cancelled_at' => $reservation->cancelled_at?->toIso8601String(),
            'completed_at' => $reservation->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve physical event sites for collection from the reservation's
     * exact event + seller + approved booking + occupying allocations only.
     *
     * @return list<array{id: int, code: string}>
     */
    private static function resolveCollectionSites(ItemReservation $reservation): array
    {
        $booking = $reservation->vendorBooking;
        if ($booking === null) {
            return [];
        }

        if ($booking->approval_status !== 'Approved') {
            return [];
        }

        if ((int) $booking->user_id !== (int) $reservation->vendor_user_id) {
            return [];
        }

        if ((int) $booking->carboot_event_id !== (int) $reservation->carboot_event_id) {
            return [];
        }

        $booking->loadMissing(['bookingDayAllocations.eventSite']);

        $eventId = (int) $reservation->carboot_event_id;

        $sites = $booking->bookingDayAllocations
            ->filter(fn ($allocation) => (int) $allocation->active_lock === 1)
            ->map(fn ($allocation) => $allocation->eventSite)
            ->filter(function ($site) use ($eventId) {
                if ($site === null) {
                    return false;
                }

                if ((int) $site->carboot_event_id !== $eventId) {
                    return false;
                }

                $code = trim((string) ($site->label ?? ''));

                return $code !== '';
            })
            ->unique('id')
            ->sort(fn ($a, $b) => strnatcasecmp((string) $a->label, (string) $b->label))
            ->values();

        return $sites->map(fn ($site) => [
            'id' => (int) $site->id,
            'code' => (string) $site->label,
        ])->all();
    }

    private static function whatsappContactForReservingUser(ItemReservation $reservation): ?array
    {
        if (! in_array($reservation->reservation_status, [
            ItemReservation::STATUS_PENDING_CHARGE,
            ItemReservation::STATUS_CONFIRMED,
        ], true)) {
            return null;
        }

        $profile = $reservation->vendorUser?->businessProfile;
        $vendorName = $profile?->business_name ?: ($reservation->vendorUser?->name ?? 'CMart Vendor');
        $itemName = (string) ($reservation->item_name_snapshot ?: 'this item');
        $reference = (string) $reservation->public_reference;
        $eventName = (string) ($reservation->carbootEvent?->title ?: 'CMart Carboot');

        return WhatsAppContact::publicContact(
            $profile,
            WhatsAppContact::reservationMessage($vendorName, $itemName, $reference, $eventName),
        );
    }
}
