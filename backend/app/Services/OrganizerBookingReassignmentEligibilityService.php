<?php

namespace App\Services;

use App\Exceptions\AllocationValidationException;
use App\Exceptions\DomainConflictException;
use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\EventDay;
use App\Models\EventSite;
use Illuminate\Support\Collection;

/**
 * Phase 3.8 — eligibility and target-site validation for Organizer reassignment.
 */
class OrganizerBookingReassignmentEligibilityService
{
    public const REASSIGNABLE_STATUSES = ['Pending_Organizer', 'Needs_Revision', 'Approved'];

    public const TERMINAL_STATUSES = ['Rejected', 'Cancelled', 'Withdrawn'];

    /**
     * @return list<array{code: string, message: string}>
     */
    public function blockingReasons(Booking $booking): array
    {
        $blockers = [];

        if (! in_array($booking->approval_status, self::REASSIGNABLE_STATUSES, true)) {
            $blockers[] = [
                'code' => 'BOOKING_NOT_REASSIGNABLE',
                'message' => 'This booking cannot be reassigned in its current state.',
            ];
        }

        if ($booking->checked_in_at !== null) {
            $blockers[] = [
                'code' => 'BOOKING_NOT_REASSIGNABLE',
                'message' => 'This booking cannot be reassigned in its current state.',
            ];
        }

        $invoice = $booking->invoice;
        if (! $invoice) {
            $blockers[] = [
                'code' => 'BOOKING_NOT_REASSIGNABLE',
                'message' => 'This booking does not have a valid invoice.',
            ];
        } elseif ($invoice->payment_status !== 'Unpaid') {
            $blockers[] = [
                'code' => 'BOOKING_PAYMENT_LOCKED',
                'message' => 'Sites cannot be changed after payment is submitted or verified.',
            ];
        }

        $active = $this->activeAllocations($booking);
        if ($active->isEmpty()) {
            $blockers[] = [
                'code' => 'BOOKING_NOT_REASSIGNABLE',
                'message' => 'This booking does not have an active allocation.',
            ];
        } elseif ($active->contains(fn (BookingDayAllocation $row) => $row->allocation_status !== BookingDayAllocation::STATUS_RESERVED)) {
            $blockers[] = [
                'code' => 'BOOKING_ALLOCATION_CONFIRMED',
                'message' => 'Sites cannot be changed because the booking allocation is confirmed.',
            ];
        }

        foreach ($this->retainedEventDays($booking) as $day) {
            if ($day->starts_at !== null && $day->starts_at->lte(now())) {
                $blockers[] = [
                    'code' => 'EVENT_DAY_ALREADY_STARTED',
                    'message' => 'Sites cannot be changed after an event day has started.',
                ];
                break;
            }
            if ($day->operational_status !== EventDay::STATUS_ACTIVE) {
                $blockers[] = [
                    'code' => 'EVENT_DAY_ALREADY_STARTED',
                    'message' => 'Sites cannot be changed after an event day has started.',
                ];
                break;
            }
        }

        return $blockers;
    }

    public function assertEligible(Booking $booking): void
    {
        $blockers = $this->blockingReasons($booking);
        if ($blockers === []) {
            return;
        }

        $code = $blockers[0]['code'];
        $message = $blockers[0]['message'];

        if (in_array($code, ['ASSIGNMENT_CHANGED', 'TARGET_SITE_UNAVAILABLE'], true)) {
            throw new DomainConflictException($message, $code);
        }

        if (in_array($code, ['BOOKING_PAYMENT_LOCKED', 'EVENT_LAYOUT_NOT_READY'], true)) {
            throw new DomainConflictException($message, $code);
        }

        throw new AllocationValidationException($message, $code);
    }

    /**
     * @param  list<int>  $targetSiteIds
     * @return Collection<int, EventSite>
     */
    public function validateTargetSites(
        Booking $booking,
        array $targetSiteIds,
        int $requiredSiteCount,
        int $requiredSpaceId,
        string $requiredUnitPrice,
    ): Collection {
        $ids = collect($targetSiteIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();

        if ($ids->count() !== count($targetSiteIds)) {
            throw new AllocationValidationException(
                'Duplicate event site IDs are not allowed.',
                'TARGET_SITE_SELECTION_INVALID',
            );
        }

        if ($ids->count() !== $requiredSiteCount) {
            throw new AllocationValidationException(
                'The new site count must match the original booking.',
                'SITE_COUNT_CHANGE_NOT_SUPPORTED',
            );
        }

        $sites = EventSite::query()
            ->with(['space', 'eventLayoutRow.vendorCategory'])
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($sites->count() !== $ids->count()) {
            throw new AllocationValidationException(
                'One or more target sites do not exist.',
                'TARGET_SITE_SELECTION_INVALID',
            );
        }

        $ordered = $ids->map(fn (int $id) => $sites->firstWhere('id', $id));

        foreach ($ordered as $site) {
            if ((int) $site->carboot_event_id !== (int) $booking->carboot_event_id) {
                throw new AllocationValidationException(
                    'Target sites must belong to the same event.',
                    'TARGET_SITE_SELECTION_INVALID',
                );
            }

            if ($site->operational_status !== EventSite::STATUS_ACTIVE) {
                throw new AllocationValidationException(
                    'One or more target sites are not active.',
                    'TARGET_SITE_UNAVAILABLE',
                );
            }

            if ($site->event_layout_row_id === null || ! $site->eventLayoutRow) {
                throw new AllocationValidationException(
                    'One or more target sites are missing a layout row.',
                    'TARGET_SITE_SELECTION_INVALID',
                );
            }

            $row = $site->eventLayoutRow;
            if (! $row->is_active || $row->archived_at !== null) {
                throw new AllocationValidationException(
                    'One or more target sites belong to an inactive row.',
                    'TARGET_SITE_UNAVAILABLE',
                );
            }

            if ($row->vendor_category_id === null || ! $row->vendorCategory) {
                throw new AllocationValidationException(
                    'One or more target rows are missing a category.',
                    'TARGET_SITE_SELECTION_INVALID',
                );
            }

            if ((int) $site->space_id !== $requiredSpaceId) {
                throw new AllocationValidationException(
                    'The new site type or price must match the original booking.',
                    'SITE_PRICE_CHANGE_NOT_SUPPORTED',
                );
            }

            $price = number_format((float) ($site->space?->price ?? 0), 2, '.', '');
            if ($price !== $requiredUnitPrice) {
                throw new AllocationValidationException(
                    'The new site type or price must match the original booking.',
                    'SITE_PRICE_CHANGE_NOT_SUPPORTED',
                );
            }
        }

        $this->assertAdjacentSameRow($ordered);
        $this->assertSingleRowCategory($ordered);

        $retainedDayIds = $this->retainedEventDayIds($booking);
        $this->assertTargetSitesAvailable($booking, $ordered, $retainedDayIds);

        return $ordered;
    }

    /**
     * @param  Collection<int, EventSite>  $sites
     */
    private function assertAdjacentSameRow(Collection $sites): void
    {
        if ($sites->count() <= 1) {
            return;
        }

        $rowIds = $sites->pluck('event_layout_row_id')->unique();
        if ($rowIds->count() !== 1) {
            throw new AllocationValidationException(
                'All sites must be selected from the same row.',
                'TARGET_SITE_MIXED_ROWS',
            );
        }

        $rowLabels = $sites->pluck('row_label')->unique();
        if ($rowLabels->count() !== 1) {
            throw new AllocationValidationException(
                'All sites must be selected from the same row.',
                'TARGET_SITE_MIXED_ROWS',
            );
        }

        $positions = $sites->sortBy('position_number')->pluck('position_number')->map(fn ($n) => (int) $n)->values();
        for ($i = 1; $i < $positions->count(); $i++) {
            if ($positions[$i] !== $positions[$i - 1] + 1) {
                throw new AllocationValidationException(
                    'The site selection does not meet layout rules.',
                    'TARGET_SITE_SELECTION_INVALID',
                );
            }
        }
    }

    /**
     * @param  Collection<int, EventSite>  $sites
     */
    private function assertSingleRowCategory(Collection $sites): void
    {
        $categoryIds = $sites
            ->map(fn (EventSite $site) => (int) $site->eventLayoutRow?->vendor_category_id)
            ->unique()
            ->filter();

        if ($categoryIds->count() > 1) {
            throw new AllocationValidationException(
                'All sites must use the same row category.',
                'TARGET_SITE_MIXED_CATEGORIES',
            );
        }
    }

    /**
     * @param  Collection<int, EventSite>  $sites
     * @param  list<int>  $retainedDayIds
     */
    private function assertTargetSitesAvailable(Booking $booking, Collection $sites, array $retainedDayIds): void
    {
        $siteIds = $sites->pluck('id')->all();

        $conflict = BookingDayAllocation::query()
            ->whereIn('event_day_id', $retainedDayIds)
            ->whereIn('event_site_id', $siteIds)
            ->where('booking_id', '!=', $booking->id)
            ->activeOccupancy()
            ->exists();

        if ($conflict) {
            throw new DomainConflictException(
                'One or more selected sites are no longer available.',
                'TARGET_SITE_UNAVAILABLE',
            );
        }
    }

    /**
     * @return Collection<int, BookingDayAllocation>
     */
    public function activeAllocations(Booking $booking): Collection
    {
        return $booking->bookingDayAllocations
            ->filter(fn (BookingDayAllocation $row) => $row->occupiesSite())
            ->sortBy('id')
            ->values();
    }

    /**
     * @return list<int>
     */
    public function retainedEventDayIds(Booking $booking): array
    {
        return $this->activeAllocations($booking)
            ->pluck('event_day_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, EventDay>
     */
    public function retainedEventDays(Booking $booking): Collection
    {
        $dayIds = $this->retainedEventDayIds($booking);

        if ($dayIds === []) {
            return collect();
        }

        return EventDay::query()
            ->whereIn('id', $dayIds)
            ->orderBy('id')
            ->get();
    }

    public function currentSiteIds(Booking $booking): array
    {
        return $this->activeAllocations($booking)
            ->pluck('event_site_id')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function requiredSpaceFromBooking(Booking $booking): array
    {
        $sites = $this->activeAllocations($booking)
            ->pluck('eventSite')
            ->filter()
            ->unique('id')
            ->values();

        $first = $sites->first();
        if (! $first || ! $first->space) {
            throw new AllocationValidationException(
                'Current booking sites are missing space metadata.',
                'BOOKING_NOT_REASSIGNABLE',
            );
        }

        return [
            'space_id' => (int) $first->space_id,
            'space_label' => $first->space->space_size,
            'unit_price' => number_format((float) $first->space->price, 2, '.', ''),
            'site_count' => $sites->count(),
        ];
    }
}
