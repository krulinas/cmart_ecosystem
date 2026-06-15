<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\VendorBookingPresenter;
use Illuminate\Http\Request;

class VendorHistoryController extends Controller
{
    /**
     * Vendor-scoped booking payment history. Never returns other vendors' records.
     */
    public function historyReceipts(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $bookings = $user->bookings()
            ->withValidBookingDate()
            ->with('invoice')
            ->orderByDesc('booking_date')
            ->orderByDesc('id')
            ->get();

        $records = $bookings
            ->map(fn (Booking $booking) => $this->formatRecord($booking))
            ->values();

        return response()->json([
            'records' => $records,
        ]);
    }

    private function formatRecord(Booking $booking): array
    {
        $invoice = $booking->invoice;
        $hasInvoice = $invoice !== null;
        $paymentStatus = $hasInvoice ? $invoice->payment_status : 'Not Issued';

        return [
            'id' => $hasInvoice ? $invoice->id : $booking->id,
            'booking_id' => $booking->id,
            'event' => VendorBookingPresenter::eventLabel($booking),
            'date' => $booking->booking_date->format('Y-m-d'),
            'booth_number' => VendorBookingPresenter::boothNumber($booking),
            'amount' => round((float) ($invoice?->amount ?? 0), 2),
            'payment_status' => $paymentStatus,
            'booking_status' => $booking->approval_status,
            'receipt_available' => $hasInvoice && $paymentStatus === 'Paid',
            'invoice_available' => $hasInvoice,
        ];
    }
}
