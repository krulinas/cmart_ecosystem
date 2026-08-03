<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Services\EventAnalyticsService;
use Illuminate\Http\Request;
use RuntimeException;

class OrganizerEventAnalyticsController extends Controller
{
    public function __construct(
        private readonly EventAnalyticsService $analytics,
    ) {}

    public function overview(CarbootEvent $event)
    {
        return response()->json($this->analytics->overview($event));
    }

    public function section(CarbootEvent $event, string $section)
    {
        try {
            return response()->json($this->analytics->section($event, $section));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function recompute(Request $request, CarbootEvent $event)
    {
        return response()->json($this->analytics->recompute($event));
    }
}
