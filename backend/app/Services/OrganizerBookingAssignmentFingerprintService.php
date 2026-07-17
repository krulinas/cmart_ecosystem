<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCategoryOverride;
use App\Models\BookingDayAllocation;
use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Phase 3.8 — deterministic optimistic concurrency token for Organizer reassignment.
 */
class OrganizerBookingAssignmentFingerprintService
{
    public function compute(Booking $booking): string
    {
        $booking->loadMissing([
            'invoice',
            'activeCategoryOverride',
            'bookingDayAllocations' => fn ($q) => $q->orderBy('id'),
        ]);

        $activeAllocations = $booking->bookingDayAllocations
            ->filter(fn (BookingDayAllocation $row) => $row->occupiesSite())
            ->sortBy('id')
            ->values();

        $payload = [
            'booking_id' => (int) $booking->id,
            'approval_status' => $booking->approval_status,
            'booking_updated_at' => $booking->updated_at?->toIso8601String(),
            'invoice_status' => $booking->invoice?->payment_status,
            'invoice_updated_at' => $booking->invoice?->updated_at?->toIso8601String(),
            'active_override_id' => $booking->activeCategoryOverride?->id,
            'active_override_status' => $booking->activeCategoryOverride?->status,
            'allocations' => $activeAllocations->map(fn (BookingDayAllocation $row) => [
                'id' => (int) $row->id,
                'status' => $row->allocation_status,
                'event_day_id' => (int) $row->event_day_id,
                'event_site_id' => (int) $row->event_site_id,
                'updated_at' => $row->updated_at?->toIso8601String(),
            ])->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function assertMatches(Booking $booking, ?string $submitted): void
    {
        if ($submitted === null || trim($submitted) === '') {
            throw new \App\Exceptions\AllocationValidationException(
                'Assignment fingerprint is required.',
                'ASSIGNMENT_FINGERPRINT_REQUIRED',
            );
        }

        $current = $this->compute($booking);

        if (! hash_equals($current, $submitted)) {
            throw new \App\Exceptions\DomainConflictException(
                'Susunan tapak telah berubah. Sila muat semula dan semak semula pilihan.',
                'ASSIGNMENT_CHANGED',
            );
        }
    }
}
