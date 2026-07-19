<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\VendorItem;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceEligibility
{
    public static function applyToVendorItemQuery(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('user.bookings', function (Builder $bookingQuery) {
                $bookingQuery
                    ->where('approval_status', 'Approved')
                    ->whereHas('carbootEvent', function (Builder $eventQuery) {
                        $eventQuery->where('ends_at', '>=', now());
                    });
            });
    }

    public static function isItemPubliclyPreviewable(VendorItem $item): bool
    {
        if ($item->status !== 'active') {
            return false;
        }

        return Booking::query()
            ->where('user_id', $item->user_id)
            ->where('approval_status', 'Approved')
            ->whereHas('carbootEvent', fn (Builder $eventQuery) => $eventQuery->where('ends_at', '>=', now()))
            ->exists();
    }

    public static function upcomingApprovedEventForUser(int $userId): ?CarbootEvent
    {
        return self::upcomingApprovedBookingForUser($userId)?->carbootEvent;
    }

    public static function upcomingApprovedBookingForUser(
        int $userId,
        bool $lockForUpdate = false,
    ): ?Booking {
        return Booking::query()
            ->with('carbootEvent')
            ->join('carboot_events', 'carboot_events.id', '=', 'bookings.carboot_event_id')
            ->where('bookings.user_id', $userId)
            ->where('bookings.approval_status', 'Approved')
            ->where('carboot_events.ends_at', '>=', now())
            ->orderBy('carboot_events.starts_at')
            ->orderBy('bookings.id')
            ->select('bookings.*')
            ->when($lockForUpdate, fn (Builder $query) => $query->lockForUpdate())
            ->first();
    }

    public static function upcomingApprovedBookingForItem(VendorItem $item): ?Booking
    {
        if ($item->relationLoaded('user') && $item->user?->relationLoaded('bookings')) {
            return $item->user->bookings
                ->filter(fn (Booking $booking) => $booking->approval_status === 'Approved'
                    && $booking->carbootEvent
                    && $booking->carbootEvent->ends_at >= now())
                ->sortBy(fn (Booking $booking) => sprintf(
                    '%s-%020d',
                    $booking->carbootEvent->starts_at->format('Y-m-d H:i:s.u'),
                    $booking->id,
                ))
                ->first();
        }

        return self::upcomingApprovedBookingForUser((int) $item->user_id);
    }
}
