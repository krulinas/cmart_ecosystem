<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\EventDay;
use App\Models\EventSite;
use Illuminate\Support\Collection;

/**
 * Structured result from BookingAllocationReservationService::reserveForBooking().
 */
class BookingAllocationReservationResult
{
    /**
     * @param  Collection<int, EventSite>  $selectedSites
     * @param  Collection<int, EventDay>  $activeEventDays
     * @param  Collection<int, BookingDayAllocation>  $allocations
     * @param  string  $amount  Decimal string safe for Invoice persistence
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly Collection $selectedSites,
        public readonly Collection $activeEventDays,
        public readonly Collection $allocations,
        public readonly int $tapakQuantity,
        public readonly string $amount,
    ) {
    }

    /**
     * @return array{
     *   booking: Booking,
     *   selected_sites: list<EventSite>,
     *   active_event_days: list<EventDay>,
     *   allocations: list<BookingDayAllocation>,
     *   tapak_quantity: int,
     *   amount: string
     * }
     */
    public function toArray(): array
    {
        return [
            'booking' => $this->booking,
            'selected_sites' => $this->selectedSites->values()->all(),
            'active_event_days' => $this->activeEventDays->values()->all(),
            'allocations' => $this->allocations->values()->all(),
            'tapak_quantity' => $this->tapakQuantity,
            'amount' => $this->amount,
        ];
    }
}
