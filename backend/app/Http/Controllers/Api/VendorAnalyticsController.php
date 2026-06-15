<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VendorAnalyticsService;
use Illuminate\Http\Request;

class VendorAnalyticsController extends Controller
{
    public function __construct(private VendorAnalyticsService $analytics)
    {
    }

    /**
     * Vendor-scoped analytics for the authenticated user only.
     * Never aggregates data across vendors.
     */
    public function me(Request $request)
    {
        $payload = $this->analytics->buildForUser($request->user());

        return response()->json([
            ...$payload,
            'items_reused' => $payload['booth']['items_reused'],
            'estimated_sales' => $payload['booth']['estimated_sales'],
            'booth_status' => $payload['booth']['booth_status'],
            'current_event' => $payload['booth']['current_event'],
            'booth_number' => $payload['booth']['booth_number'],
        ]);
    }

    public function report(Request $request)
    {
        return response()->json(
            $this->analytics->buildReportForUser($request->user()),
        );
    }
}
