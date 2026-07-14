<?php

namespace App\Services;

use App\Exceptions\AllocationValidationException;
use App\Exceptions\DomainConflictException;
use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2A.6/2A.7 — transactional full-event site reservation wired to POST /bookings.
 */
class BookingAllocationReservationService
{
    /**
     * Reserve inside an existing outer transaction (Phase 2A.7 booking creation).
     *
     * @param  list<int|string>  $eventSiteIds
     *
     * @throws AllocationValidationException
     * @throws DomainConflictException
     */
    public function reserveForBookingInExistingTransaction(
        Booking $booking,
        array $eventSiteIds,
    ): BookingAllocationReservationResult {
        try {
            return $this->reserveInsideTransaction($booking, $eventSiteIds);
        } catch (QueryException $exception) {
            $this->rethrowQueryException($exception);
        }
    }

    /**
     * @param  list<int|string>  $eventSiteIds
     *
     * @throws AllocationValidationException
     * @throws DomainConflictException
     */
    public function reserveForBooking(Booking $booking, array $eventSiteIds): BookingAllocationReservationResult
    {
        try {
            return DB::transaction(function () use ($booking, $eventSiteIds) {
                return $this->reserveInsideTransaction($booking, $eventSiteIds);
            });
        } catch (QueryException $exception) {
            $this->rethrowQueryException($exception);
        }
    }

    /**
     * @param  list<int|string>  $eventSiteIds
     */
    private function reserveInsideTransaction(Booking $booking, array $eventSiteIds): BookingAllocationReservationResult
    {
        // 1–2. Reload and lock Booking.
        $booking = Booking::query()
            ->whereKey($booking->id)
            ->lockForUpdate()
            ->firstOrFail();

        // 3. Require valid carboot_event_id.
        if (! $booking->carboot_event_id) {
            throw new AllocationValidationException(
                'Booking has no valid Carboot event.',
                'missing_carboot_event',
            );
        }

        // 4. Lock parent CarbootEvent.
        $event = CarbootEvent::query()
            ->whereKey($booking->carboot_event_id)
            ->lockForUpdate()
            ->firstOrFail();

        // 5–7. Non-empty, no duplicates, deterministic ID order.
        $siteIds = $this->normalizeSiteIds($eventSiteIds);

        // 8–10. Load EventSites with Space; order by ID; lock.
        $sites = EventSite::query()
            ->with('space')
            ->whereIn('id', $siteIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // 11. Every requested ID must exist.
        if ($sites->count() !== count($siteIds)) {
            throw new AllocationValidationException(
                'One or more event sites do not exist.',
                'missing_event_site',
            );
        }

        // Re-order loaded sites to match sorted ID list for deterministic validation.
        $sitesById = $sites->keyBy('id');
        $orderedSites = collect($siteIds)->map(fn (int $id) => $sitesById->get($id));

        // 12–16. Event ownership, active status, adjacency, same space type.
        $this->assertSitesBelongToEvent($orderedSites, (int) $event->id);
        $this->assertSitesAreActive($orderedSites);
        $this->assertSitesAreAdjacentAndSameType($orderedSites);

        // 17–20. Active EventDays, ordered by ID, locked; require at least one.
        $activeDays = EventDay::query()
            ->forEvent((int) $event->id)
            ->active()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($activeDays->isEmpty()) {
            throw new AllocationValidationException(
                'This event has no active operational days configured. The Organizer must configure the event schedule before bookings can be accepted.',
                'no_active_event_days',
            );
        }

        // 21. Booking must not already have allocation history.
        if (BookingDayAllocation::query()->forBooking((int) $booking->id)->exists()) {
            throw new DomainConflictException(
                'This booking already has allocation history and cannot be reserved again.',
                'booking_already_allocated',
            );
        }

        // 22–23. Full day × site set; reject any active occupancy.
        $this->assertNoActiveOccupancy($activeDays, $orderedSites);

        // 24–25. Derive quantity and amount from selected sites only.
        $tapakQuantity = $orderedSites->count();
        $amount = $this->deriveAmount($orderedSites);

        // 26–29. Create reserved allocations with one reserved_at, deterministic insert order.
        $reservedAt = now();
        $allocations = collect();

        foreach ($activeDays->sortBy('id')->values() as $day) {
            foreach ($orderedSites->sortBy('id')->values() as $site) {
                $allocations->push(BookingDayAllocation::create([
                    'booking_id' => $booking->id,
                    'event_day_id' => $day->id,
                    'event_site_id' => $site->id,
                    'allocation_status' => BookingDayAllocation::STATUS_RESERVED,
                    'reserved_at' => $reservedAt,
                    'confirmed_at' => null,
                    'released_at' => null,
                    'released_by' => null,
                    'release_reason' => null,
                    'active_lock' => BookingDayAllocation::activeLockForStatus(
                        BookingDayAllocation::STATUS_RESERVED
                    ),
                ]));
            }
        }

        return new BookingAllocationReservationResult(
            booking: $booking->fresh(),
            selectedSites: $orderedSites->values(),
            activeEventDays: $activeDays->values(),
            allocations: $allocations->values(),
            tapakQuantity: $tapakQuantity,
            amount: $amount,
        );
    }

    /**
     * @param  list<int|string>  $eventSiteIds
     * @return list<int>
     */
    private function normalizeSiteIds(array $eventSiteIds): array
    {
        if ($eventSiteIds === []) {
            throw new AllocationValidationException(
                'At least one event site must be selected.',
                'empty_event_sites',
            );
        }

        $ids = array_map('intval', $eventSiteIds);

        if (count($ids) !== count(array_unique($ids))) {
            throw new AllocationValidationException(
                'Duplicate event site IDs are not allowed.',
                'duplicate_event_site_ids',
            );
        }

        sort($ids, SORT_NUMERIC);

        return array_values($ids);
    }

    /**
     * @param  Collection<int, EventSite>  $sites
     */
    private function assertSitesBelongToEvent(Collection $sites, int $eventId): void
    {
        foreach ($sites as $site) {
            if ((int) $site->carboot_event_id !== $eventId) {
                throw new AllocationValidationException(
                    'One or more event sites do not belong to this booking\'s event.',
                    'event_site_wrong_event',
                );
            }
        }
    }

    /**
     * @param  Collection<int, EventSite>  $sites
     */
    private function assertSitesAreActive(Collection $sites): void
    {
        foreach ($sites as $site) {
            if ($site->operational_status !== EventSite::STATUS_ACTIVE) {
                throw new AllocationValidationException(
                    'One or more selected event sites are not operationally active.',
                    'event_site_inactive',
                );
            }
        }
    }

    /**
     * Multi-site: same row, consecutive positions, same space_id.
     *
     * @param  Collection<int, EventSite>  $sites
     */
    private function assertSitesAreAdjacentAndSameType(Collection $sites): void
    {
        if ($sites->count() <= 1) {
            return;
        }

        $byPosition = $sites->sortBy('position_number')->values();

        $rowLabels = $byPosition->pluck('row_label')->unique()->values();
        if ($rowLabels->count() !== 1) {
            throw new AllocationValidationException(
                'Selected event sites must share the same row label.',
                'mixed_rows',
            );
        }

        $spaceIds = $byPosition->pluck('space_id')->unique()->values();
        if ($spaceIds->count() !== 1) {
            throw new AllocationValidationException(
                'Selected event sites must share the same space type.',
                'mixed_space_types',
            );
        }

        $positions = $byPosition->pluck('position_number')->map(fn ($n) => (int) $n)->values();
        for ($i = 1; $i < $positions->count(); $i++) {
            if ($positions[$i] !== $positions[$i - 1] + 1) {
                throw new AllocationValidationException(
                    'Selected event sites must have consecutive position numbers.',
                    'non_consecutive_positions',
                );
            }
        }
    }

    /**
     * @param  Collection<int, EventDay>  $days
     * @param  Collection<int, EventSite>  $sites
     */
    private function assertNoActiveOccupancy(Collection $days, Collection $sites): void
    {
        $dayIds = $days->pluck('id')->all();
        $siteIds = $sites->pluck('id')->all();

        $conflict = BookingDayAllocation::query()
            ->whereIn('event_day_id', $dayIds)
            ->whereIn('event_site_id', $siteIds)
            ->activeOccupancy()
            ->exists();

        if ($conflict) {
            throw new DomainConflictException(
                'One or more selected sites are already reserved or confirmed for an operational day.',
                'site_day_occupied',
            );
        }
    }

    /**
     * @param  Collection<int, EventSite>  $sites
     */
    private function deriveAmount(Collection $sites): string
    {
        $total = '0.00';

        foreach ($sites as $site) {
            if (! $site->space) {
                throw new AllocationValidationException(
                    'One or more event sites are missing a space type for pricing.',
                    'missing_space_price',
                );
            }

            $price = number_format((float) $site->space->price, 2, '.', '');
            $total = bcadd($total, $price, 2);
        }

        return $total;
    }

    /**
     * @throws DomainConflictException
     */
    private function rethrowQueryException(QueryException $exception): never
    {
        if ($this->isActiveOccupancyViolation($exception)) {
            throw new DomainConflictException(
                'One or more selected sites are no longer available for an operational day.',
                'site_day_occupied',
            );
        }

        if ($this->isDuplicateBookingAllocationViolation($exception)) {
            throw new DomainConflictException(
                'This booking already has allocation rows for one or more selected site-days.',
                'duplicate_booking_allocation',
            );
        }

        throw $exception;
    }

    private function isActiveOccupancyViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            && str_contains($exception->getMessage(), 'bda_day_site_active_lock_unique');
    }

    private function isDuplicateBookingAllocationViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            && str_contains($exception->getMessage(), 'bda_booking_day_site_unique');
    }
}
