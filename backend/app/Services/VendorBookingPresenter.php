<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CarbootEvent;

class VendorBookingPresenter
{
    private const WITHDRAWABLE_STATUSES = ['Pending_Staff', 'Pending_Boss', 'Needs_Revision'];

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

    public static function presentForVendor(Booking $booking, ?int $viewerUserId = null): array
    {
        $booking->loadMissing(['space', 'invoice']);

        return array_merge($booking->toArray(), [
            'can_withdraw' => self::canVendorWithdraw($booking, $viewerUserId),
            'event_label' => self::eventLabel($booking),
        ]);
    }
}
