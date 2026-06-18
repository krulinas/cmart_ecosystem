<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\VendorEventPassService;
use Illuminate\Http\Request;

class BookingPassVerificationController extends Controller
{
    public function __construct(private VendorEventPassService $passes)
    {
    }

    public function verify(Request $request, Booking $booking)
    {
        $booking->load(['user', 'space']);

        $result = $this->passes->verifyForStaff($booking);

        return response()->json($result, $result['valid'] ? 200 : 422);
    }

    public function checkIn(Request $request, Booking $booking)
    {
        $booking->load(['user', 'space']);

        $result = $this->passes->checkIn($booking);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
