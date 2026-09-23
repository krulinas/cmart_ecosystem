<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Services\OrganizerEventBenchmarkService;
use Illuminate\Http\Request;

class OrganizerEventBenchmarkController extends Controller
{
    public function __construct(
        private readonly OrganizerEventBenchmarkService $benchmarks,
    ) {}

    public function show(Request $request, CarbootEvent $event)
    {
        $organizerId = $request->user()?->id;

        return response()->json(
            $this->benchmarks->forEvent($event, $organizerId ? (int) $organizerId : null)
        );
    }
}
