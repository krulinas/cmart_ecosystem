<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Safe event picker for CMart report-request forms (no carboot CRUD access required).
 */
class CmartReportEventOptionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CarbootEvent::query()
            ->select(['id', 'title', 'starts_at', 'ends_at', 'status'])
            ->orderByDesc('starts_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->boolean('closed_or_past')) {
            $query->where(function ($inner) {
                $inner->where('status', 'Closed')
                    ->orWhere(function ($past) {
                        $past->whereNotNull('ends_at')->where('ends_at', '<', now());
                    });
            });
        }

        $events = $query->limit(200)->get()->map(fn (CarbootEvent $event) => [
            'id' => $event->id,
            'title' => $event->title,
            'starts_at' => optional($event->starts_at)?->toIso8601String(),
            'ends_at' => optional($event->ends_at)?->toIso8601String(),
            'status' => $event->status,
        ]);

        return response()->json(['data' => $events]);
    }
}
