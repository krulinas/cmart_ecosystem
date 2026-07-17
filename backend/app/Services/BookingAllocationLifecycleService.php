<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Phase 2A.7 — allocation status transitions tied to booking lifecycle.
 */
class BookingAllocationLifecycleService
{
    public const REASON_BOOKING_REJECTED = 'booking_rejected';

    public const REASON_BOOKING_CANCELLED = 'booking_cancelled';

    public const REASON_BOOKING_WITHDRAWN = 'booking_withdrawn';

    public const REASON_ORGANIZER_DAY_EXCEPTION = 'organizer_day_exception';

    public const REASON_ORGANIZER_SITE_REASSIGNMENT = 'organizer_site_reassignment';

    /**
     * Transition active reserved allocations to confirmed after payment verification.
     *
     * @return Collection<int, BookingDayAllocation>
     *
     * @throws DomainConflictException
     */
    public function confirmForBooking(Booking $booking): Collection
    {
        $booking = Booking::query()
            ->whereKey($booking->id)
            ->lockForUpdate()
            ->firstOrFail();

        $allocations = BookingDayAllocation::query()
            ->forBooking((int) $booking->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($allocations->isEmpty()) {
            return collect();
        }

        $active = $allocations->filter(fn (BookingDayAllocation $row) => $row->occupiesSite());

        if ($active->isEmpty()) {
            throw new DomainConflictException(
                'Allocations for this booking have already been released and cannot be confirmed.',
                'allocations_already_released',
            );
        }

        $reserved = $active->where('allocation_status', BookingDayAllocation::STATUS_RESERVED);
        $confirmed = $active->where('allocation_status', BookingDayAllocation::STATUS_CONFIRMED);

        if ($reserved->isEmpty() && $confirmed->count() === $active->count()) {
            return $active->values();
        }

        if ($reserved->count() !== $active->count()) {
            throw new DomainConflictException(
                'Allocations for this booking are in a mixed lifecycle state and cannot be confirmed.',
                'invalid_allocation_lifecycle',
            );
        }

        $confirmedAt = now();

        foreach ($reserved->sortBy('id')->values() as $allocation) {
            $allocation->update([
                'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
                'confirmed_at' => $confirmedAt,
            ]);
        }

        return BookingDayAllocation::query()
            ->forBooking((int) $booking->id)
            ->activeOccupancy()
            ->orderBy('id')
            ->get();
    }

    /**
     * Release active reserved or confirmed allocations (rejection, cancellation, withdrawal).
     *
     * @return Collection<int, BookingDayAllocation>
     *
     * @throws DomainConflictException
     */
    public function releaseForBooking(Booking $booking, ?User $releasedBy, string $reason): Collection
    {
        $booking = Booking::query()
            ->whereKey($booking->id)
            ->lockForUpdate()
            ->firstOrFail();

        $allocations = BookingDayAllocation::query()
            ->forBooking((int) $booking->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($allocations->isEmpty()) {
            return collect();
        }

        $active = $allocations->filter(fn (BookingDayAllocation $row) => $row->occupiesSite());

        if ($active->isEmpty()) {
            return $allocations
                ->where('allocation_status', BookingDayAllocation::STATUS_RELEASED)
                ->values();
        }

        $releasedAt = now();

        foreach ($active->sortBy('id')->values() as $allocation) {
            $allocation->update([
                'allocation_status' => BookingDayAllocation::STATUS_RELEASED,
                'released_at' => $releasedAt,
                'released_by' => $releasedBy?->id,
                'release_reason' => $reason,
                'active_lock' => null,
            ]);
        }

        return BookingDayAllocation::query()
            ->forBooking((int) $booking->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Release only active allocations on the selected EventDays.
     *
     * @param  list<int>  $eventDayIds
     * @return Collection<int, BookingDayAllocation>
     */
    public function releaseForBookingDays(
        Booking $booking,
        array $eventDayIds,
        User $releasedBy,
        string $reason = self::REASON_ORGANIZER_DAY_EXCEPTION,
    ): Collection {
        $booking = Booking::query()
            ->whereKey($booking->id)
            ->lockForUpdate()
            ->firstOrFail();

        $dayIds = array_values(array_unique(array_map('intval', $eventDayIds)));
        sort($dayIds, SORT_NUMERIC);

        if ($dayIds === []) {
            return collect();
        }

        $allocations = BookingDayAllocation::query()
            ->forBooking((int) $booking->id)
            ->whereIn('event_day_id', $dayIds)
            ->activeOccupancy()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($allocations->isEmpty()) {
            return collect();
        }

        $releasedAt = now();

        foreach ($allocations as $allocation) {
            $allocation->update([
                'allocation_status' => BookingDayAllocation::STATUS_RELEASED,
                'released_at' => $releasedAt,
                'released_by' => $releasedBy->id,
                'release_reason' => $reason,
                'active_lock' => null,
            ]);
        }

        return $allocations->map->fresh()->values();
    }
}
