<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\StaffOperationsController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ManagementReportsController extends Controller
{
    /**
     * Generated operational overview for CMart Management and Organizer roles.
     * Excludes raw revenue analytics — counts only.
     */
    public function operationalOverview(): JsonResponse
    {
        return app(StaffOperationsController::class)->operationsSummary();
    }
}
