<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AllocationValidationException;
use App\Http\Controllers\Controller;
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
        try {
            return response()->json(
                $availabilityService->forEvent($carboot_event),
            );
        } catch (AllocationValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 422);
        }
    }
}
