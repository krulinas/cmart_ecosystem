<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\User;
use App\Models\VendorItem;
use App\Models\VendorItemEventListing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendorItemEventListingService
{
    /**
     * @param  list<int>  $vendorItemIds
     * @return Collection<int, VendorItemEventListing>
     */
    public function selectItems(User $vendor, int $eventId, array $vendorItemIds): Collection
    {
        $booking = $this->approvedBookingForVendorEvent($vendor, $eventId);

        return DB::transaction(function () use ($vendor, $eventId, $vendorItemIds, $booking) {
            $event = CarbootEvent::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            if ($event->ends_at < now()) {
                throw new DomainConflictException(
                    'Items cannot be selected for an event that has already ended.',
                    'event_already_ended',
                );
            }

            $ids = collect($vendorItemIds)->map(fn ($id) => (int) $id)->unique()->filter()->values();
            if ($ids->isEmpty()) {
                throw new DomainConflictException(
                    'Select at least one item for this event.',
                    'no_items_selected',
                );
            }

            $items = VendorItem::query()
                ->where('user_id', $vendor->id)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== $ids->count()) {
                throw (new ModelNotFoundException)->setModel(VendorItem::class, $ids->all());
            }

            $created = collect();

            foreach ($ids as $itemId) {
                /** @var VendorItem $item */
                $item = $items->get($itemId);

                if ($item->hasSale()) {
                    throw new DomainConflictException(
                        'Sold items cannot be selected for an event.',
                        'item_already_sold',
                    );
                }

                if ($item->status !== 'active') {
                    throw new DomainConflictException(
                        'Only visible (active) items can be selected for an event.',
                        'item_not_visible',
                    );
                }

                $listing = VendorItemEventListing::query()->updateOrCreate(
                    [
                        'vendor_item_id' => $item->id,
                        'carboot_event_id' => $event->id,
                    ],
                    [
                        'vendor_booking_id' => $booking->id,
                        'vendor_user_id' => $vendor->id,
                        'selected_by' => $vendor->id,
                        'selected_at' => now(),
                    ],
                );

                $created->push($listing);
            }

            return $created->values();
        });
    }

    public function selectAllUnsold(User $vendor, int $eventId): Collection
    {
        $ids = VendorItem::query()
            ->where('user_id', $vendor->id)
            ->where('status', 'active')
            ->whereDoesntHave('sale')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return collect();
        }

        return $this->selectItems($vendor, $eventId, $ids);
    }

    public function deselectItem(User $vendor, int $eventId, int $vendorItemId): void
    {
        $this->approvedBookingForVendorEvent($vendor, $eventId);

        DB::transaction(function () use ($vendor, $eventId, $vendorItemId) {
            $item = VendorItem::query()
                ->where('user_id', $vendor->id)
                ->whereKey($vendorItemId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->reservations()
                ->where('carboot_event_id', $eventId)
                ->where('active_lock', 1)
                ->exists()) {
                throw new DomainConflictException(
                    'This item has an active reservation for the event and cannot be removed.',
                    'item_has_active_reservation',
                );
            }

            VendorItemEventListing::query()
                ->where('vendor_item_id', $item->id)
                ->where('carboot_event_id', $eventId)
                ->where('vendor_user_id', $vendor->id)
                ->delete();
        });
    }

    public function listingsForVendorEvent(User $vendor, int $eventId): Collection
    {
        $this->approvedBookingForVendorEvent($vendor, $eventId);

        return VendorItemEventListing::query()
            ->with(['vendorItem.images', 'carbootEvent', 'vendorBooking'])
            ->where('vendor_user_id', $vendor->id)
            ->where('carboot_event_id', $eventId)
            ->orderBy('selected_at')
            ->get();
    }

    public function eligibleEventsForVendor(User $vendor): Collection
    {
        return Booking::query()
            ->with('carbootEvent')
            ->where('user_id', $vendor->id)
            ->where('approval_status', 'Approved')
            ->whereHas('carbootEvent', fn ($q) => $q->where('ends_at', '>=', now()))
            ->join('carboot_events', 'carboot_events.id', '=', 'bookings.carboot_event_id')
            ->orderBy('carboot_events.starts_at')
            ->select('bookings.*')
            ->get();
    }

    private function approvedBookingForVendorEvent(User $vendor, int $eventId): Booking
    {
        $booking = Booking::query()
            ->with('carbootEvent')
            ->where('user_id', $vendor->id)
            ->where('carboot_event_id', $eventId)
            ->where('approval_status', 'Approved')
            ->first();

        if (! $booking) {
            throw new AuthorizationException(
                'An Approved booking for this event is required to select items.',
            );
        }

        return $booking;
    }
}
