<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use App\Services\VendorBookingPresenter;
use Illuminate\Http\Request;

class VendorAnalyticsController extends Controller
{
    /**
     * Vendor-scoped analytics for the authenticated user only.
     * Never aggregates data across vendors.
     */
    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        // TODO: Requires a vendor_items / booth_sales_items table linked to user_id
        // to count items sold, reused, or logged at the booth. No such table exists yet.
        $itemsReused = 0;

        $estimatedSales = (float) Invoice::query()
            ->where('payment_status', 'Paid')
            ->whereHas('booking', fn ($q) => $q->where('user_id', $user->id))
            ->sum('amount');

        $currentBooking = $this->resolveCurrentBooking($user);

        return response()->json([
            'items_reused' => $itemsReused,
            'estimated_sales' => round($estimatedSales, 2),
            'booth_status' => $this->resolveBoothStatus($currentBooking),
            'current_event' => $currentBooking
                ? VendorBookingPresenter::eventLabel($currentBooking)
                : null,
            'booth_number' => $currentBooking
                ? VendorBookingPresenter::boothNumber($currentBooking)
                : null,
        ]);
    }

    private function resolveCurrentBooking(User $user): ?Booking
    {
        $today = now()->toDateString();

        return $user->bookings()
            ->withValidBookingDate()
            ->whereNotIn('approval_status', ['Rejected', 'Cancelled'])
            ->whereDate('booking_date', '>=', $today)
            ->orderBy('booking_date')
            ->orderBy('id')
            ->first();
    }

    private function resolveBoothStatus(?Booking $booking): string
    {
        if (!$booking) {
            return 'No Active Booking';
        }

        // TODO: Requires bookings.checked_in_at (or equivalent) to return "Checked-in".
        // QR verification exists in the frontend but no persisted check-in flag yet.
        if ($booking->approval_status === 'Approved') {
            return 'Approved';
        }

        if (in_array($booking->approval_status, ['Pending_Staff', 'Pending_Boss', 'Needs_Revision'], true)) {
            return 'Pending';
        }

        return 'No Active Booking';
    }
}
