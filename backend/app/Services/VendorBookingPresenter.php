<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;

class VendorBookingPresenter
{
    private const WITHDRAWABLE_STATUSES = ['Pending_Organizer', 'Needs_Revision'];

    public static function eventLabel(Booking $booking): string
    {
        if ($booking->relationLoaded('carbootEvent') && $booking->carbootEvent) {
            return $booking->carbootEvent->title;
        }

        $event = CarbootEvent::query()
            ->whereDate('starts_at', $booking->booking_date)
            ->orderBy('starts_at')
            ->first();

        if ($event) {
            return $event->title;
        }

        return $booking->booking_date->format('j M Y');
    }

    public static function boothNumber(Booking $booking): ?string
    {
        if ($booking->approval_status !== 'Approved') {
            return null;
        }

        $booking->loadMissing([
            'bookingDayAllocations.eventSite',
        ]);

        $sites = $booking->bookingDayAllocations
            ->pluck('eventSite')
            ->filter()
            ->unique('id')
            ->sortBy('label')
            ->values();

        if ($sites->isNotEmpty()) {
            return $sites->pluck('label')->implode(', ');
        }

        $prefix = chr(65 + ($booking->id % 3));

        return sprintf('%s-%02d', $prefix, $booking->id);
    }

    public static function canVendorWithdraw(Booking $booking, ?int $viewerUserId = null): bool
    {
        if ($viewerUserId === null || $booking->user_id !== $viewerUserId) {
            return false;
        }

        if (!in_array($booking->approval_status, self::WITHDRAWABLE_STATUSES, true)) {
            return false;
        }

        if ($booking->invoice && $booking->invoice->payment_status === 'Paid') {
            return false;
        }

        return true;
    }

    public static function siteSelection(Booking $booking): ?array
    {
        $booking->loadMissing([
            'bookingDayAllocations.eventSite.space',
            'bookingDayAllocations.eventDay',
        ]);

        $allocations = $booking->bookingDayAllocations;

        if ($allocations->isEmpty()) {
            return null;
        }

        $sites = $allocations
            ->pluck('eventSite')
            ->filter()
            ->unique('id')
            ->sortBy('label')
            ->values()
            ->map(fn ($site) => [
                'id' => $site->id,
                'label' => $site->label,
                'row_label' => $site->row_label,
                'position_number' => $site->position_number,
                'space_id' => $site->space_id,
                'space_name' => $site->space?->space_size,
                'price' => number_format((float) ($site->space?->price ?? 0), 2, '.', ''),
            ])
            ->values()
            ->all();

        $days = $allocations
            ->pluck('eventDay')
            ->filter()
            ->unique('id')
            ->sortBy('operational_date')
            ->values()
            ->map(fn ($day) => [
                'id' => $day->id,
                'operational_date' => $day->operational_date->format('Y-m-d'),
                'starts_at' => $day->starts_at?->toIso8601String(),
                'ends_at' => $day->ends_at?->toIso8601String(),
                'operational_status' => $day->operational_status,
            ])
            ->values()
            ->all();

        $activeAllocations = $allocations->filter(fn ($row) => $row->occupiesSite());
        $allocationStatus = null;

        if ($activeAllocations->isNotEmpty()) {
            $statuses = $activeAllocations->pluck('allocation_status')->unique()->values();
            $allocationStatus = $statuses->count() === 1 ? $statuses->first() : 'mixed';
        } elseif ($allocations->isNotEmpty()) {
            $allocationStatus = $allocations->first()->allocation_status;
        }

        return [
            'site_count' => count($sites),
            'active_day_count' => count($days),
            'allocation_count' => $allocations->count(),
            'allocation_status' => $allocationStatus,
            'sites' => $sites,
            'days' => $days,
        ];
    }

    public static function tapakQuantity(Booking $booking): ?int
    {
        $selection = self::siteSelection($booking);

        return $selection['site_count'] ?? null;
    }

    public static function presentForVendor(Booking $booking, ?int $viewerUserId = null): array
    {
        $booking->loadMissing(['space', 'invoice', 'carbootEvent']);

        $payload = array_merge($booking->toArray(), [
            'can_withdraw' => self::canVendorWithdraw($booking, $viewerUserId),
            'event_label' => self::eventLabel($booking),
        ]);

        $siteSelection = self::siteSelection($booking);
        if ($siteSelection !== null) {
            $payload['site_selection'] = $siteSelection;
            $payload['tapak_quantity'] = $siteSelection['site_count'];
        }

        return $payload;
    }

    public static function presentForOrganizer(Booking $booking): array
    {
        $booking->loadMissing(['user', 'space', 'invoice', 'carbootEvent']);

        $payload = $booking->toArray();

        $siteSelection = self::siteSelection($booking);
        if ($siteSelection !== null) {
            $payload['site_selection'] = $siteSelection;
            $payload['tapak_quantity'] = $siteSelection['site_count'];
        }

        return $payload;
    }
}
