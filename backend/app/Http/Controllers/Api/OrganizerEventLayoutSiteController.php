<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Services\EventLayoutLockService;
use App\Services\EventLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Phase 3.5 — Organizer layout site lifecycle and row-aware generation.
 */
class OrganizerEventLayoutSiteController extends Controller
{
    public function __construct(
        private readonly EventLayoutService $layout,
        private readonly EventLayoutLockService $locks,
    ) {
    }

    public function store(Request $request, CarbootEvent $carboot_event, EventLayoutRow $row): JsonResponse
    {
        if (! $this->rowBelongsToEvent($row, $carboot_event)) {
            return $this->rowNotFound();
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/'],
            'space_id' => 'required|integer|exists:spaces,id',
            'position_number' => 'required|integer|min:1',
            'grid_row' => 'required|integer|min:0',
            'grid_column' => 'required|integer|min:0',
            'display_order' => 'sometimes|integer|min:0',
            'status' => ['sometimes', 'string', Rule::in(EventSite::OPERATIONAL_STATUSES)],
            'operational_status' => ['sometimes', 'string', Rule::in(EventSite::OPERATIONAL_STATUSES)],
            'metadata' => 'nullable|array',
        ]);

        try {
            $site = $this->layout->createSite($row, $request->user(), $validated);
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        return response()->json([
            'message' => '201 Created: Event site created successfully.',
            'site' => $this->present($site),
        ], 201);
    }

    public function generate(Request $request, CarbootEvent $carboot_event, EventLayoutRow $row): JsonResponse
    {
        if (! $this->rowBelongsToEvent($row, $carboot_event)) {
            return $this->rowNotFound();
        }

        $validated = $request->validate([
            'space_id' => 'required|integer|exists:spaces,id',
            'count' => 'required|integer|min:1|max:100',
            'label_prefix' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/'],
            'start_number' => 'sometimes|integer|min:1',
            'number_padding' => 'sometimes|integer|min:1|max:6',
            'start_grid_column' => 'sometimes|integer|min:0',
            'grid_row' => 'sometimes|integer|min:0',
            'display_order_start' => 'sometimes|integer|min:0',
        ]);

        try {
            $result = $this->layout->generateSites($row, $request->user(), $validated);
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        $sites = collect($result['created'])
            ->each(fn (EventSite $site) => $site->load('space'))
            ->map(fn (EventSite $site) => $this->present($site))
            ->values();

        return response()->json([
            'message' => '201 Created: Row sites generated successfully.',
            'created_count' => $sites->count(),
            'sites' => $sites,
        ], 201);
    }

    public function update(Request $request, CarbootEvent $carboot_event, EventSite $site): JsonResponse
    {
        if (! $this->siteBelongsToEvent($site, $carboot_event)) {
            return $this->siteNotFound();
        }

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/'],
            'event_layout_row_id' => 'sometimes|integer|exists:event_layout_rows,id',
            'space_id' => 'sometimes|integer|exists:spaces,id',
            'position_number' => 'sometimes|integer|min:1',
            'grid_row' => 'sometimes|integer|min:0',
            'grid_column' => 'sometimes|integer|min:0',
            'display_order' => 'sometimes|integer|min:0',
            'status' => ['sometimes', 'string', Rule::in(EventSite::OPERATIONAL_STATUSES)],
            'operational_status' => ['sometimes', 'string', Rule::in(EventSite::OPERATIONAL_STATUSES)],
        ]);

        try {
            $site = $this->layout->updateSite($site, $request->user(), $validated);
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        return response()->json([
            'message' => '200 OK: Event site updated successfully.',
            'site' => $this->present($site),
        ]);
    }

    public function reorder(Request $request, CarbootEvent $carboot_event, EventLayoutRow $row): JsonResponse
    {
        if (! $this->rowBelongsToEvent($row, $carboot_event)) {
            return $this->rowNotFound();
        }

        $validated = $request->validate([
            'sites' => 'required|array|min:1',
            'sites.*.id' => 'required|integer',
            'sites.*.display_order' => 'required|integer|min:0',
        ]);

        try {
            $this->layout->reorderSites($row, $request->user(), $validated['sites']);
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        return response()->json([
            'message' => '200 OK: Layout sites reordered successfully.',
        ]);
    }

    public function destroy(Request $request, CarbootEvent $carboot_event, EventSite $site): JsonResponse
    {
        if (! $this->siteBelongsToEvent($site, $carboot_event)) {
            return $this->siteNotFound();
        }

        try {
            $this->layout->deleteSite($site, $request->user());
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'message' => '200 OK: Event site deleted successfully.',
        ]);
    }

    private function present(EventSite $site): array
    {
        $site->loadMissing(['space', 'eventLayoutRow']);

        return [
            'id' => $site->id,
            'label' => $site->label,
            'row_label' => $site->row_label,
            'event_layout_row_id' => $site->event_layout_row_id,
            'position_number' => $site->position_number,
            'grid_row' => $site->grid_row,
            'grid_column' => $site->grid_column,
            'display_order' => $site->display_order,
            'operational_status' => $site->operational_status,
            'occupancy' => $this->locks->siteOccupancySummary($site),
            'space' => $site->space ? [
                'id' => $site->space->id,
                'space_size' => $site->space->space_size,
                'price' => (float) $site->space->price,
                'status' => $site->space->status,
            ] : null,
            'locks' => $this->locks->siteLocks($site),
        ];
    }

    private function rowBelongsToEvent(EventLayoutRow $row, CarbootEvent $event): bool
    {
        return (int) $row->carboot_event_id === (int) $event->id;
    }

    private function siteBelongsToEvent(EventSite $site, CarbootEvent $event): bool
    {
        return (int) $site->carboot_event_id === (int) $event->id;
    }

    private function conflict(DomainConflictException $exception): JsonResponse
    {
        return response()->json([
            'message' => '409 Conflict: ' . $exception->getMessage(),
            'error' => $exception->error,
        ], 409);
    }

    private function validationError(InvalidArgumentException $exception): JsonResponse
    {
        $message = $exception->getMessage();
        $error = 'INVALID_SITE_LABEL';
        $lower = strtolower($message);
        if (str_contains($lower, 'count')) {
            $error = 'INVALID_SITE_COUNT';
        } elseif (str_contains($lower, 'status')) {
            $error = 'INVALID_SITE_STATUS';
        } elseif (str_contains($lower, 'category')) {
            $error = 'CATEGORY_INACTIVE';
        } elseif (str_contains($lower, 'display_order')) {
            $error = 'INVALID_DISPLAY_ORDER';
        }

        return response()->json([
            'message' => '422 Unprocessable Entity: ' . $message,
            'error' => $error,
        ], 422);
    }

    private function rowNotFound(): JsonResponse
    {
        return response()->json([
            'message' => '404 Not Found: Layout row not found for this event.',
        ], 404);
    }

    private function siteNotFound(): JsonResponse
    {
        return response()->json([
            'message' => '404 Not Found: Event site not found for this event.',
        ], 404);
    }
}
