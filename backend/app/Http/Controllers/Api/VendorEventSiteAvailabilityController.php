<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AllocationValidationException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Services\VendorEventSiteAvailabilityService;
use Illuminate\Http\Request;

class VendorEventSiteAvailabilityController extends Controller
{
    public function show(
        Request $request,
        CarbootEvent $carboot_event,
        VendorEventSiteAvailabilityService $availabilityService,
    ) {
        $vendorCategoryId = $request->query('vendor_category_id');
        $legacyCategory = $request->query('product_category');
        $bookingId = $request->query('booking_id');

        $bookingContext = null;
        if ($bookingId !== null && $bookingId !== '') {
            $booking = Booking::query()->find((int) $bookingId);
            if (! $booking || (int) $booking->user_id !== (int) $request->user()->id) {
                return response()->json([
                    'message' => '403 Forbidden: Booking context is not accessible.',
                    'error' => 'BOOKING_CONTEXT_FORBIDDEN',
                ], 403);
            }
            if ((int) $booking->carboot_event_id !== (int) $carboot_event->id) {
                return response()->json([
                    'message' => '422 Unprocessable Entity: Booking does not belong to this event.',
                    'error' => 'BOOKING_EVENT_MISMATCH',
                ], 422);
            }
            $bookingContext = $booking;
        }

        try {
            return response()->json(
                $availabilityService->forEvent(
                    $carboot_event,
                    $vendorCategoryId !== null && $vendorCategoryId !== '' ? (int) $vendorCategoryId : null,
                    is_string($legacyCategory) ? $legacyCategory : null,
                    $bookingContext,
                ),
            );
        } catch (AllocationValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 422);
        }
    }
}
