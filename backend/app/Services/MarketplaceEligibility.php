<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\VendorItem;
use App\Models\VendorItemEventListing;
use App\Models\VendorItemSale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class MarketplaceEligibility
{
    public static function applyToVendorItemQuery(Builder $query, ?int $eventId = null): Builder
    {
        $query = $query
            ->where('status', 'active')
            ->whereDoesntHave('sale')
            ->whereDoesntHave('reservations', fn (Builder $q) => $q->where('active_lock', 1));

        if (! Schema::hasTable('vendor_item_event_selections')) {
            // Pre-migration fallback: preserve previous Approved-booking heuristic.
            return $query->whereHas('user.bookings', function (Builder $bookingQuery) use ($eventId) {
                $bookingQuery
                    ->where('approval_status', 'Approved')
                    ->when($eventId, fn (Builder $q) => $q->where('carboot_event_id', $eventId))
                    ->whereHas('carbootEvent', function (Builder $eventQuery) use ($eventId) {
                        $eventQuery
                            ->where('ends_at', '>=', now())
                            ->when($eventId, fn (Builder $q) => $q->whereKey($eventId));
                    });
            });
        }

        return $query->whereHas('eventListings', function (Builder $listingQuery) use ($eventId) {
            $listingQuery
                ->when($eventId, fn (Builder $q) => $q->where('carboot_event_id', $eventId))
                ->whereHas('vendorBooking', fn (Builder $q) => $q->where('approval_status', 'Approved'))
                ->whereHas('carbootEvent', function (Builder $eventQuery) use ($eventId) {
                    $eventQuery
                        ->where('ends_at', '>=', now())
                        ->when($eventId, fn (Builder $q) => $q->whereKey($eventId));
                });
        });
    }

    public static function isItemPubliclyPreviewable(VendorItem $item, ?int $eventId = null): bool
    {
        if ($item->status !== 'active' || $item->hasSale()) {
            return false;
        }

        if ($item->hasActiveReservationFlag()) {
            return false;
        }

        if (! Schema::hasTable('vendor_item_event_selections')) {
            return Booking::query()
                ->where('user_id', $item->user_id)
                ->where('approval_status', 'Approved')
                ->when($eventId, fn (Builder $q) => $q->where('carboot_event_id', $eventId))
                ->whereHas('carbootEvent', fn (Builder $eventQuery) => $eventQuery->where('ends_at', '>=', now()))
                ->exists();
        }

        return VendorItemEventListing::query()
            ->where('vendor_item_id', $item->id)
            ->when($eventId, fn (Builder $q) => $q->where('carboot_event_id', $eventId))
            ->whereHas('vendorBooking', fn (Builder $q) => $q->where('approval_status', 'Approved'))
            ->whereHas('carbootEvent', fn (Builder $eventQuery) => $eventQuery->where('ends_at', '>=', now()))
            ->exists();
    }

    public static function listingForItemEvent(int $vendorItemId, int $eventId, bool $lock = false): ?VendorItemEventListing
    {
        if (! Schema::hasTable('vendor_item_event_selections')) {
            return null;
        }

        return VendorItemEventListing::query()
            ->with(['carbootEvent', 'vendorBooking'])
            ->where('vendor_item_id', $vendorItemId)
            ->where('carboot_event_id', $eventId)
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())
            ->first();
    }

    public static function resolvePreviewBooking(VendorItem $item, ?int $eventId = null): ?Booking
    {
        if ($eventId) {
            if ($item->relationLoaded('eventListings')) {
                $listing = $item->eventListings
                    ->first(fn ($candidate) => (int) $candidate->carboot_event_id === (int) $eventId);

                if ($listing) {
                    $listing->loadMissing(['carbootEvent', 'vendorBooking']);
                    if ($listing->vendorBooking?->approval_status === 'Approved'
                        && $listing->carbootEvent
                        && $listing->carbootEvent->ends_at >= now()) {
                        return $listing->vendorBooking->setRelation('carbootEvent', $listing->carbootEvent);
                    }
                }

                return null;
            }

            $listing = self::listingForItemEvent((int) $item->id, $eventId);
            if ($listing?->vendorBooking?->approval_status === 'Approved'
                && $listing->carbootEvent
                && $listing->carbootEvent->ends_at >= now()) {
                return $listing->vendorBooking->setRelation('carbootEvent', $listing->carbootEvent);
            }

            return null;
        }

        if (! Schema::hasTable('vendor_item_event_selections')) {
            return self::upcomingApprovedBookingForUser((int) $item->user_id);
        }

        if ($item->relationLoaded('eventListings')) {
            $listing = $item->eventListings
                ->filter(function ($candidate) {
                    $candidate->loadMissing(['carbootEvent', 'vendorBooking']);

                    return $candidate->vendorBooking?->approval_status === 'Approved'
                        && $candidate->carbootEvent
                        && $candidate->carbootEvent->ends_at >= now();
                })
                ->sortBy(fn ($candidate) => [
                    optional($candidate->carbootEvent?->starts_at)->timestamp ?? PHP_INT_MAX,
                    $candidate->id,
                ])
                ->first();

            return $listing
                ? $listing->vendorBooking?->setRelation('carbootEvent', $listing->carbootEvent)
                : null;
        }

        $listing = VendorItemEventListing::query()
            ->with(['carbootEvent', 'vendorBooking'])
            ->where('vendor_item_id', $item->id)
            ->whereHas('vendorBooking', fn (Builder $q) => $q->where('approval_status', 'Approved'))
            ->whereHas('carbootEvent', fn (Builder $q) => $q->where('ends_at', '>=', now()))
            ->join('carboot_events', 'carboot_events.id', '=', 'vendor_item_event_selections.carboot_event_id')
            ->orderBy('carboot_events.starts_at')
            ->orderBy('vendor_item_event_selections.id')
            ->select('vendor_item_event_selections.*')
            ->first();

        if (! $listing) {
            return null;
        }

        return $listing->vendorBooking?->setRelation('carbootEvent', $listing->carbootEvent);
    }

    public static function upcomingApprovedEventForUser(int $userId): ?CarbootEvent
    {
        return self::upcomingApprovedBookingForUser($userId)?->carbootEvent;
    }

    public static function upcomingApprovedBookingForUser(
        int $userId,
        bool $lockForUpdate = false,
    ): ?Booking {
        return Booking::query()
            ->with('carbootEvent')
            ->join('carboot_events', 'carboot_events.id', '=', 'bookings.carboot_event_id')
            ->where('bookings.user_id', $userId)
            ->where('bookings.approval_status', 'Approved')
            ->where('carboot_events.ends_at', '>=', now())
            ->orderBy('carboot_events.starts_at')
            ->orderBy('bookings.id')
            ->select('bookings.*')
            ->when($lockForUpdate, fn (Builder $query) => $query->lockForUpdate())
            ->first();
    }

    /** @deprecated Prefer resolvePreviewBooking / listingForItemEvent */
    public static function upcomingApprovedBookingForItem(VendorItem $item): ?Booking
    {
        return self::resolvePreviewBooking($item);
    }

    public static function removeListingsForItem(int $vendorItemId): void
    {
        if (! Schema::hasTable('vendor_item_event_selections')) {
            return;
        }

        VendorItemEventListing::query()
            ->where('vendor_item_id', $vendorItemId)
            ->delete();
    }

    public static function itemIsSold(int $vendorItemId): bool
    {
        if (! Schema::hasTable('vendor_item_sales')) {
            return false;
        }

        return VendorItemSale::query()->where('vendor_item_id', $vendorItemId)->exists();
    }
}
