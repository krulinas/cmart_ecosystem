<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\VendorEventPassService;
use Illuminate\Http\Request;

class VendorEventPassController extends Controller
{
    public function __construct(private VendorEventPassService $passes)
    {
    }

    public function index(Request $request)
    {
        return response()->json($this->passes->listForUser($request->user()->id));
    }

    public function show(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => '403 Forbidden: You do not own this booking pass.'], 403);
        }

        return response()->json([
            'pass' => $this->passes->presentPass($booking->load(['space', 'invoice'])),
        ]);
    }
}
