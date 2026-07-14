<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\DomainConflictException;
use App\Models\CarbootEvent;
use App\Models\EventSite;
use App\Models\Space;
use App\Services\EventSiteLayoutGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Phase 2A.4 — Organizer CRUD for event-scoped physical parking sites.
 */
class EventSiteController extends Controller
{
    public function index(CarbootEvent $carboot_event): JsonResponse
    {
        $sites = EventSite::query()
            ->forEvent($carboot_event->id)
            ->with('space:id,space_size,price,status')
            ->orderedForLayout()
            ->get()
            ->map(fn (EventSite $site) => $this->present($site))
            ->values();

        return response()->json([
            'event_id' => $carboot_event->id,
            'event_title' => $carboot_event->title,
            'sites' => $sites,
            'meta' => [
                'total' => $sites->count(),
                'active' => $sites->where('operational_status', EventSite::STATUS_ACTIVE)->count(),
            ],
        ]);
    }

    public function store(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        $validated = $this->validateSite($request);

        try {
            $site = EventSite::create([
                ...$validated,
                'carboot_event_id' => $carboot_event->id,
            ]);
        } catch (QueryException $exception) {
            return $this->uniqueConstraintResponse($exception);
        }

        $site->load('space:id,space_size,price,status');

        return response()->json([
            'message' => '201 Created: Event site created successfully.',
            'site' => $this->present($site),
        ], 201);
    }

    public function show(EventSite $event_site): JsonResponse
    {
        $event_site->load(['space:id,space_size,price,status', 'carbootEvent:id,title']);

        return response()->json([
            'site' => $this->present($event_site),
        ]);
    }

    public function update(Request $request, EventSite $event_site): JsonResponse
    {
        if ($event_site->hasAllocationHistory()) {
            $blocked = $this->structuralFieldsAttempted($request, $event_site);
            if ($blocked !== []) {
                return response()->json([
                    'message' => '409 Conflict: Event site structural identity cannot change after allocation history exists.',
                    'error' => 'event_site_history_structural_lock',
                    'blocked_fields' => $blocked,
                ], 409);
            }
        }

        $validated = $this->validateSite($request, true, $event_site);

        try {
            $event_site->update($validated);
        } catch (QueryException $exception) {
            return $this->uniqueConstraintResponse($exception);
        }

        $event_site->load('space:id,space_size,price,status');

        return response()->json([
            'message' => '200 OK: Event site updated successfully.',
            'site' => $this->present($event_site->fresh('space')),
        ]);
    }

    public function generate(Request $request, CarbootEvent $carboot_event, EventSiteLayoutGenerator $generator): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => 'required|integer|exists:spaces,id',
            'rows' => 'required|array|min:1|max:50',
            'rows.*.row_label' => 'required|string|max:16',
            'rows.*.count' => 'required|integer|min:1|max:100',
            'rows.*.start_position' => 'sometimes|integer|min:1',
            'rows.*.grid_row' => 'sometimes|integer|min:0',
            'rows.*.grid_column_start' => 'sometimes|integer|min:0',
            'rows.*.label_prefix' => 'nullable|string|max:16',
            'replace_existing' => 'sometimes|boolean',
        ]);

        try {
            $result = $generator->generate(
                $carboot_event,
                (int) $validated['space_id'],
                $validated['rows'],
                (bool) ($validated['replace_existing'] ?? false),
            );
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => '409 Conflict: ' . $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => '422 Unprocessable Entity: ' . $exception->getMessage(),
            ], 422);
        }

        $sites = collect($result['created'])
            ->each(fn (EventSite $site) => $site->load('space:id,space_size,price,status'))
            ->map(fn (EventSite $site) => $this->present($site))
            ->values();

        return response()->json([
            'message' => '201 Created: Event parking layout generated successfully.',
            'event_id' => $carboot_event->id,
            'replaced' => $result['replaced'],
            'created_count' => $sites->count(),
            'sites' => $sites,
        ], 201);
    }

    public function destroy(EventSite $event_site): JsonResponse
    {
        if ($event_site->hasAllocationHistory()) {
            return response()->json([
                'message' => '409 Conflict: Event site has allocation history and cannot be deleted.',
                'error' => 'event_site_has_allocation_history',
            ], 409);
        }

        $event_site->delete();

        return response()->json([
            'message' => '200 OK: Event site deleted successfully.',
        ]);
    }

    /**
     * Structural identity fields frozen once any BookingDayAllocation references this site.
     *
     * @return list<string>
     */
    private function structuralFieldsAttempted(Request $request, EventSite $site): array
    {
        $structural = [
            'space_id',
            'label',
            'row_label',
            'position_number',
            'grid_row',
            'grid_column',
        ];

        $blocked = [];
        foreach ($structural as $field) {
            if (! $request->exists($field)) {
                continue;
            }

            $incoming = $request->input($field);
            if ($field === 'label' || $field === 'row_label') {
                $incoming = strtoupper(trim((string) $incoming));
            }

            if ((string) $incoming !== (string) $site->{$field}) {
                $blocked[] = $field;
            }
        }

        return $blocked;
    }

    private function validateSite(Request $request, bool $partial = false, ?EventSite $existing = null): array
    {
        $required = $partial ? 'sometimes|' : '';

        $validated = $request->validate([
            'space_id' => [
                $partial ? 'sometimes' : 'required',
                'integer',
                'exists:spaces,id',
            ],
            'label' => [
                $required . 'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/',
            ],
            'row_label' => [
                $required . 'required',
                'string',
                'max:16',
            ],
            'position_number' => [
                $required . 'required',
                'integer',
                'min:1',
            ],
            'grid_row' => [
                $required . 'required',
                'integer',
                'min:0',
            ],
            'grid_column' => [
                $required . 'required',
                'integer',
                'min:0',
            ],
            'display_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
            'operational_status' => [
                $partial ? 'sometimes' : 'nullable',
                'string',
                Rule::in(EventSite::OPERATIONAL_STATUSES),
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ]);

        if (isset($validated['label'])) {
            $validated['label'] = strtoupper(trim($validated['label']));
        }

        if (isset($validated['row_label'])) {
            $validated['row_label'] = strtoupper(trim($validated['row_label']));
        }

        if (! isset($validated['operational_status']) && ! $partial) {
            $validated['operational_status'] = EventSite::STATUS_ACTIVE;
        }

        if (! isset($validated['display_order']) && ! $partial) {
            $validated['display_order'] = (int) ($validated['position_number'] ?? $existing?->position_number ?? 0);
        }

        if (isset($validated['space_id'])) {
            Space::query()->findOrFail($validated['space_id']);
        }

        return $validated;
    }

    private function present(EventSite $site): array
    {
        return [
            'id' => $site->id,
            'carboot_event_id' => $site->carboot_event_id,
            'event_title' => $site->relationLoaded('carbootEvent')
                ? $site->carbootEvent?->title
                : null,
            'space_id' => $site->space_id,
            'space' => $site->relationLoaded('space') && $site->space
                ? [
                    'id' => $site->space->id,
                    'space_size' => $site->space->space_size,
                    'price' => (float) $site->space->price,
                    'status' => $site->space->status,
                ]
                : null,
            'label' => $site->label,
            'row_label' => $site->row_label,
            'position_number' => $site->position_number,
            'grid_row' => $site->grid_row,
            'grid_column' => $site->grid_column,
            'display_order' => $site->display_order,
            'operational_status' => $site->operational_status,
            'metadata' => $site->metadata,
            'created_at' => optional($site->created_at)?->toIso8601String(),
            'updated_at' => optional($site->updated_at)?->toIso8601String(),
        ];
    }

    private function uniqueConstraintResponse(QueryException $exception): JsonResponse
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'event_sites_event_label_unique')) {
            return response()->json([
                'message' => '422 Unprocessable Entity: An event site with this label already exists for this event.',
                'error' => 'duplicate_label',
            ], 422);
        }

        if (str_contains($message, 'event_sites_event_row_position_unique')) {
            return response()->json([
                'message' => '422 Unprocessable Entity: An event site with this row and position already exists for this event.',
                'error' => 'duplicate_row_position',
            ], 422);
        }

        throw $exception;
    }
}
