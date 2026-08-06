<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Models\EventLayoutRow;
use App\Services\EventLayoutLockService;
use App\Services\EventLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Phase 3.5 — Organizer layout row lifecycle.
 */
class OrganizerEventLayoutRowController extends Controller
{
    public function __construct(
        private readonly EventLayoutService $layout,
        private readonly EventLayoutLockService $locks,
    ) {
    }

    public function store(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:32',
            'vendor_category_id' => 'required|integer|exists:vendor_categories,id',
            'space_id' => 'required|integer|exists:spaces,id',
            'description' => 'nullable|string|max:1000',
            'display_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
        ]);

        try {
            $row = $this->layout->createRow($carboot_event, $request->user(), $validated);
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        return response()->json([
            'message' => '201 Created: Layout row created successfully.',
            'row' => $this->present($row),
        ], 201);
    }

    public function update(Request $request, CarbootEvent $carboot_event, EventLayoutRow $row): JsonResponse
    {
        if (! $this->rowBelongsToEvent($row, $carboot_event)) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'label' => 'sometimes|string|max:32',
            'vendor_category_id' => 'sometimes|integer|exists:vendor_categories,id',
            'description' => 'nullable|string|max:1000',
            'display_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
        ]);

        try {
            $row = $this->layout->updateRow($row, $request->user(), $validated);
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        return response()->json([
            'message' => '200 OK: Layout row updated successfully.',
            'row' => $this->present($row),
        ]);
    }

    public function reorder(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.id' => 'required|integer',
            'rows.*.display_order' => 'required|integer|min:0',
        ]);

        try {
            $this->layout->reorderRows($carboot_event, $request->user(), $validated['rows']);
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        return response()->json([
            'message' => '200 OK: Layout rows reordered successfully.',
        ]);
    }

    public function destroy(Request $request, CarbootEvent $carboot_event, EventLayoutRow $row): JsonResponse
    {
        if (! $this->rowBelongsToEvent($row, $carboot_event)) {
            return $this->notFound();
        }

        try {
            $this->layout->deleteEmptyRow($row, $request->user());
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'message' => '200 OK: Layout row deleted successfully.',
        ]);
    }

    public function archive(Request $request, CarbootEvent $carboot_event, EventLayoutRow $row): JsonResponse
    {
        if (! $this->rowBelongsToEvent($row, $carboot_event)) {
            return $this->notFound();
        }

        try {
            $row = $this->layout->archiveRow($row, $request->user());
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'message' => '200 OK: Layout row archived successfully.',
            'row' => $this->present($row),
        ]);
    }

    public function unarchive(Request $request, CarbootEvent $carboot_event, EventLayoutRow $row): JsonResponse
    {
        if (! $this->rowBelongsToEvent($row, $carboot_event)) {
            return $this->notFound();
        }

        try {
            $result = $this->layout->unarchiveRow($row, $request->user());
        } catch (DomainConflictException $exception) {
            return $this->conflict($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        return response()->json([
            'message' => '200 OK: Layout row unarchived successfully.',
            'row' => $this->present($result['row']),
            'readiness' => $result['readiness'],
        ]);
    }

    private function rowBelongsToEvent(EventLayoutRow $row, CarbootEvent $event): bool
    {
        return (int) $row->carboot_event_id === (int) $event->id;
    }

    private function present(EventLayoutRow $row): array
    {
        $row->loadMissing('vendorCategory');
        $category = $row->vendorCategory;

        return [
            'id' => $row->id,
            'label' => $row->label,
            'slug' => $row->slug,
            'description' => $row->description,
            'display_order' => $row->display_order,
            'is_active' => $row->is_active,
            'is_public' => $row->is_public,
            'archived_at' => optional($row->archived_at)?->toIso8601String(),
            'outside_venue_template' => ! \App\Support\CmartCarbootPhysicalLayout::isAllowedRowLabel((string) $row->label),
            'category' => $category ? [
                'id' => $category->id,
                'slug' => $category->slug,
                'label' => $category->label,
                'is_active' => $category->is_active,
                'is_public' => $category->is_public,
            ] : null,
            'locks' => $this->locks->rowLocks($row),
        ];
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
        $error = 'INVALID_LAYOUT_ROW';
        if (str_contains(strtolower($message), 'category')) {
            $error = str_contains(strtolower($message), 'inactive') || str_contains(strtolower($message), 'archived')
                ? 'CATEGORY_INACTIVE'
                : 'INVALID_VENDOR_CATEGORY';
        }

        return response()->json([
            'message' => '422 Unprocessable Entity: ' . $message,
            'error' => $error,
        ], 422);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'message' => '404 Not Found: Layout row not found for this event.',
        ], 404);
    }
}
