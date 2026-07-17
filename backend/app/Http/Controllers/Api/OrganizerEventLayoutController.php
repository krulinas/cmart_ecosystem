<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Models\EventLayoutAuditLog;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Services\EventLayoutAuditLogger;
use App\Services\EventLayoutLockService;
use App\Services\EventLayoutReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.5 — Organizer event layout projection and readiness.
 */
class OrganizerEventLayoutController extends Controller
{
    public function __construct(
        private readonly EventLayoutReadinessService $readiness,
        private readonly EventLayoutLockService $locks,
        private readonly EventLayoutAuditLogger $audit,
    ) {
    }

    public function show(CarbootEvent $carboot_event): JsonResponse
    {
        $readiness = $this->readiness->assess($carboot_event);
        $eventLocks = $this->locks->eventLockSummary($carboot_event->id);

        $rows = EventLayoutRow::query()
            ->forEvent($carboot_event->id)
            ->with(['vendorCategory', 'eventSites.space'])
            ->ordered()
            ->get();

        $allSiteIds = $rows
            ->flatMap(fn (EventLayoutRow $row) => $row->eventSites->pluck('id'))
            ->all();
        $occupancy = $this->locks->occupancyBySiteIds($allSiteIds);

        $rowPayload = $rows->map(function (EventLayoutRow $row) use ($occupancy) {
            $sites = $row->eventSites
                ->sortBy([
                    ['display_order', 'asc'],
                    ['grid_column', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->map(fn (EventSite $site) => $this->presentSite($site, $occupancy[$site->id] ?? 'available'));

            return [
                'id' => $row->id,
                'label' => $row->label,
                'slug' => $row->slug,
                'description' => $row->description,
                'display_order' => $row->display_order,
                'is_active' => $row->is_active,
                'is_public' => $row->is_public,
                'archived_at' => optional($row->archived_at)?->toIso8601String(),
                'category' => $this->presentCategory($row),
                'locks' => $this->locks->rowLocks($row),
                'sites' => $sites,
            ];
        })->values();

        $unresolved = EventSite::query()
            ->forEvent($carboot_event->id)
            ->whereNull('event_layout_row_id')
            ->with('space')
            ->orderedForLayout()
            ->get();
        $unresolvedOccupancy = $this->locks->occupancyBySiteIds($unresolved->pluck('id')->all());

        return response()->json([
            'event' => [
                'id' => $carboot_event->id,
                'name' => $carboot_event->title,
                'status' => $carboot_event->status,
                'public_layout_published' => $carboot_event->public_layout_published_at !== null,
                'public_layout_published_at' => $carboot_event->public_layout_published_at?->toIso8601String(),
                'public_layout_entrance_note' => $carboot_event->public_layout_entrance_note,
            ],
            'readiness' => [
                'operational_ready' => $readiness['operational_ready'],
                'public_ready' => $readiness['public_ready'],
                'blocking_reasons' => $readiness['blocking_reasons'],
            ],
            'locks' => $eventLocks,
            'rows' => $rowPayload,
            'unresolved_sites' => $unresolved
                ->map(fn (EventSite $site) => $this->presentSite(
                    $site,
                    $unresolvedOccupancy[$site->id] ?? 'available',
                ))
                ->values(),
        ]);
    }

    public function readiness(CarbootEvent $carboot_event): JsonResponse
    {
        return response()->json($this->readiness->assess($carboot_event));
    }

    public function publish(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        $validated = $request->validate([
            'entrance_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $readiness = $this->readiness->assess($carboot_event);

        if (! $readiness['public_ready']) {
            return response()->json([
                'message' => 'Susun atur belum bersedia untuk diterbitkan.',
                'error' => 'PUBLIC_LAYOUT_NOT_PUBLISHABLE',
                'blocking_reasons' => $readiness['blocking_reasons'],
            ], 422);
        }

        return DB::transaction(function () use ($request, $carboot_event, $validated) {
            $event = CarbootEvent::query()->whereKey($carboot_event->id)->lockForUpdate()->firstOrFail();
            $before = [
                'published_at' => $event->public_layout_published_at?->toIso8601String(),
                'entrance_note' => $event->public_layout_entrance_note,
            ];
            $event->update([
                'public_layout_published_at' => now(),
                'public_layout_entrance_note' => $validated['entrance_note'] ?? null,
            ]);

            $this->audit->record(
                eventId: (int) $event->id,
                actor: $request->user(),
                action: EventLayoutAuditLog::ACTION_LAYOUT_PUBLISHED,
                before: $before,
                after: [
                    'published_at' => $event->public_layout_published_at?->toIso8601String(),
                    'entrance_note' => $event->public_layout_entrance_note,
                ],
            );

            return response()->json([
                'message' => 'Susun atur awam telah diterbitkan.',
                'publication' => [
                    'published' => true,
                    'published_at' => $event->public_layout_published_at?->toIso8601String(),
                    'entrance_note' => $event->public_layout_entrance_note,
                ],
            ]);
        });
    }

    public function unpublish(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        return DB::transaction(function () use ($request, $carboot_event) {
            $event = CarbootEvent::query()->whereKey($carboot_event->id)->lockForUpdate()->firstOrFail();
            $before = [
                'published_at' => $event->public_layout_published_at?->toIso8601String(),
                'entrance_note' => $event->public_layout_entrance_note,
            ];
            $event->update(['public_layout_published_at' => null]);

            $this->audit->record(
                eventId: (int) $event->id,
                actor: $request->user(),
                action: EventLayoutAuditLog::ACTION_LAYOUT_UNPUBLISHED,
                before: $before,
                after: [
                    'published_at' => null,
                    'entrance_note' => $event->public_layout_entrance_note,
                ],
            );

            return response()->json([
                'message' => 'Susun atur awam telah dinyahterbitkan.',
                'publication' => [
                    'published' => false,
                    'published_at' => null,
                    'entrance_note' => $event->public_layout_entrance_note,
                ],
            ]);
        });
    }

    private function presentCategory(EventLayoutRow $row): ?array
    {
        $category = $row->vendorCategory;
        if (! $category) {
            return null;
        }

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'label' => $category->label,
            'is_active' => $category->is_active,
            'is_public' => $category->is_public,
        ];
    }

    private function presentSite(EventSite $site, string $occupancy): array
    {
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
            'occupancy' => $occupancy,
            'space' => $site->relationLoaded('space') && $site->space
                ? [
                    'id' => $site->space->id,
                    'space_size' => $site->space->space_size,
                    'price' => (float) $site->space->price,
                    'status' => $site->space->status,
                ]
                : null,
            'locks' => $this->locks->siteLocks($site),
        ];
    }
}
