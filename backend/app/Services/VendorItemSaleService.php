<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\ItemReservation;
use App\Models\ItemReservationAudit;
use App\Models\User;
use App\Models\VendorItem;
use App\Models\VendorItemEventListing;
use App\Models\VendorItemSale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VendorItemSaleService
{
    public function completeReservedSale(
        ItemReservation $reservation,
        User $actor,
        string|float|int $finalSalePrice,
    ): VendorItemSale {
        $this->assertVendorOrOrganizer($reservation, $actor);
        $price = $this->normalizePrice($finalSalePrice);

        try {
            return DB::transaction(function () use ($reservation, $actor, $price) {
                $locked = ItemReservation::query()
                    ->whereKey($reservation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->reservation_status === ItemReservation::STATUS_COMPLETED) {
                    $existing = VendorItemSale::query()
                        ->where('item_reservation_id', $locked->id)
                        ->first();
                    if ($existing) {
                        return $existing;
                    }
                }

                if ($locked->reservation_status !== ItemReservation::STATUS_CONFIRMED) {
                    throw new DomainConflictException(
                        'Only a confirmed reservation may be marked collected and sold.',
                        'reservation_not_confirmed',
                    );
                }

                $item = VendorItem::query()
                    ->whereKey($locked->vendor_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($item->hasSale()) {
                    throw new DomainConflictException(
                        'This item already has a recorded sale.',
                        'item_already_sold',
                    );
                }

                $sale = $this->createSaleRecord([
                    'vendor_item_id' => $item->id,
                    'vendor_user_id' => $item->user_id,
                    'carboot_event_id' => $locked->carboot_event_id,
                    'vendor_booking_id' => $locked->vendor_booking_id,
                    'item_reservation_id' => $locked->id,
                    'sale_source' => VendorItemSale::SOURCE_RESERVED,
                    'item_name_snapshot' => $locked->item_name_snapshot ?: $item->name,
                    'asking_price_snapshot' => $item->pricing_type === 'fixed' ? $item->price : null,
                    'final_sale_price' => $price,
                    'currency' => 'MYR',
                    'sold_at' => now(),
                    'recorded_by_user_id' => $actor->id,
                ]);

                $fromStatus = $locked->reservation_status;
                $fromCharge = $locked->charge_status;

                $locked->update([
                    'reservation_status' => ItemReservation::STATUS_COMPLETED,
                    'active_lock' => null,
                    'completed_by' => $actor->id,
                    'completed_at' => now(),
                ]);

                $item->update(['status' => 'inactive']);
                MarketplaceEligibility::removeListingsForItem((int) $item->id);

                ItemReservationAudit::query()->create([
                    'item_reservation_id' => $locked->id,
                    'actor_user_id' => $actor->id,
                    'action' => ItemReservationAudit::ACTION_COMPLETED,
                    'from_reservation_status' => $fromStatus,
                    'to_reservation_status' => ItemReservation::STATUS_COMPLETED,
                    'from_charge_status' => $fromCharge,
                    'to_charge_status' => $fromCharge,
                    'note' => 'Collection confirmed and item marked as sold.',
                    'metadata' => [
                        'sale_id' => $sale->id,
                        'final_sale_price' => $price,
                        'sale_source' => VendorItemSale::SOURCE_RESERVED,
                        'item_status' => 'inactive',
                    ],
                ]);

                return $sale->fresh(['vendorItem', 'carbootEvent']);
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateSale($exception)) {
                $existing = VendorItemSale::query()
                    ->where('item_reservation_id', $reservation->id)
                    ->orWhere('vendor_item_id', $reservation->vendor_item_id)
                    ->first();
                if ($existing) {
                    return $existing;
                }

                throw new DomainConflictException(
                    'This item already has a recorded sale.',
                    'item_already_sold',
                );
            }

            throw $exception;
        }
    }

    public function recordWalkInSale(
        VendorItem $item,
        User $actor,
        int $eventId,
        string|float|int $finalSalePrice,
    ): VendorItemSale {
        if ((int) $item->user_id !== (int) $actor->id) {
            throw new AuthorizationException('You do not own this item.');
        }

        $price = $this->normalizePrice($finalSalePrice);

        try {
            return DB::transaction(function () use ($item, $actor, $eventId, $price) {
                $lockedItem = VendorItem::query()
                    ->whereKey($item->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedItem->hasSale()) {
                    throw new DomainConflictException(
                        'This item already has a recorded sale.',
                        'item_already_sold',
                    );
                }

                if ($lockedItem->reservations()->where('active_lock', 1)->exists()) {
                    throw new DomainConflictException(
                        'Resolve the active reservation before recording a walk-in sale.',
                        'item_has_active_reservation',
                    );
                }

                $listing = VendorItemEventListing::query()
                    ->with(['vendorBooking', 'carbootEvent'])
                    ->where('vendor_item_id', $lockedItem->id)
                    ->where('carboot_event_id', $eventId)
                    ->where('vendor_user_id', $actor->id)
                    ->lockForUpdate()
                    ->first();

                if (! $listing) {
                    throw new DomainConflictException(
                        'This item was not selected for the chosen event.',
                        'item_not_selected_for_event',
                    );
                }

                if ($listing->vendorBooking?->approval_status !== 'Approved') {
                    throw new DomainConflictException(
                        'An Approved booking is required for the chosen event.',
                        'booking_not_approved',
                    );
                }

                $sale = $this->createSaleRecord([
                    'vendor_item_id' => $lockedItem->id,
                    'vendor_user_id' => $lockedItem->user_id,
                    'carboot_event_id' => $eventId,
                    'vendor_booking_id' => $listing->vendor_booking_id,
                    'item_reservation_id' => null,
                    'sale_source' => VendorItemSale::SOURCE_WALK_IN,
                    'item_name_snapshot' => $lockedItem->name,
                    'asking_price_snapshot' => $lockedItem->pricing_type === 'fixed' ? $lockedItem->price : null,
                    'final_sale_price' => $price,
                    'currency' => 'MYR',
                    'sold_at' => now(),
                    'recorded_by_user_id' => $actor->id,
                ]);

                $lockedItem->update(['status' => 'inactive']);
                MarketplaceEligibility::removeListingsForItem((int) $lockedItem->id);

                return $sale->fresh(['vendorItem', 'carbootEvent']);
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateSale($exception)) {
                $existing = VendorItemSale::query()->where('vendor_item_id', $item->id)->first();
                if ($existing) {
                    return $existing;
                }

                throw new DomainConflictException(
                    'This item already has a recorded sale.',
                    'item_already_sold',
                );
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSaleRecord(array $attributes): VendorItemSale
    {
        return VendorItemSale::query()->create($attributes);
    }

    private function normalizePrice(string|float|int $value): string
    {
        if (! is_numeric($value)) {
            throw new DomainConflictException(
                'Final sale price must be a non-negative number.',
                'invalid_final_sale_price',
            );
        }

        $normalized = number_format((float) $value, 2, '.', '');
        if (bccomp($normalized, '0.00', 2) < 0) {
            throw new DomainConflictException(
                'Final sale price must be a non-negative number.',
                'invalid_final_sale_price',
            );
        }

        return $normalized;
    }

    private function assertVendorOrOrganizer(ItemReservation $reservation, User $actor): void
    {
        $isVendor = (int) $reservation->vendor_user_id === (int) $actor->id;
        $isOrganizer = in_array($actor->role, ['organizer', 'admin', 'boss', 'cmart_management'], true);

        if (! $isVendor && ! $isOrganizer) {
            throw new AuthorizationException('You cannot complete this reservation.');
        }
    }

    private function isDuplicateSale(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'vendor_item_sales_item_unique')
            || str_contains($message, 'vendor_item_sales_reservation_unique')
            || str_contains($message, 'Duplicate entry');
    }
}
