<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CarbootEvent;

class VendorBookingPresenter
{
    public static function eventLabel(Booking $booking): string
    {
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

        $prefix = chr(65 + ($booking->id % 3));

        return sprintf('%s-%02d', $prefix, $booking->id);
    }
}
