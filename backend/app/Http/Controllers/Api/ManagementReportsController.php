<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\StaffOperationsController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ManagementReportsController extends Controller
{
    /**
     * Operational Overview — live Organizer queue counts (not a published generated report).
     * Excludes raw revenue analytics — counts only. Organizer-equivalent roles only.
     */
    public function operationalOverview(): JsonResponse
    {
        return app(StaffOperationsController::class)->operationsSummary();
    }
}
