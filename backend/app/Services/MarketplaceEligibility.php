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
        return CarbootEvent::query()
            ->where('ends_at', '>=', now())
            ->whereHas('bookings', function (Builder $bookingQuery) use ($userId) {
                $bookingQuery
                    ->where('user_id', $userId)
                    ->where('approval_status', 'Approved');
            })
            ->orderBy('starts_at')
            ->first();
    }
}
