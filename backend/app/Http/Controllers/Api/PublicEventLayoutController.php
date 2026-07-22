<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Services\PublicEventLayoutService;
use Illuminate\Http\JsonResponse;

class PublicEventLayoutController extends Controller
{
    public function show(int $event, PublicEventLayoutService $layouts): JsonResponse
    {
        $carbootEvent = CarbootEvent::query()->find($event);

        if (! $carbootEvent) {
            return response()->json([
                'layout_available' => false,
                'message' => 'Event not found.',
                'error' => 'PUBLIC_EVENT_NOT_FOUND',
            ], 404);
        }

        $payload = $layouts->present($carbootEvent);
        if ($payload === null) {
            return response()->json([
                'layout_available' => false,
                'event' => [
                    'id' => (int) $carbootEvent->id,
                    'name' => $carbootEvent->title,
                ],
                'rows' => [],
                'message' => 'The event layout has not been published.',
                'error' => 'PUBLIC_LAYOUT_NOT_AVAILABLE',
            ], 404);
        }

        return response()->json($payload);
    }
}
