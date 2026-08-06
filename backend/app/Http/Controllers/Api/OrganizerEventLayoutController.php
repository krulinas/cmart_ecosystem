<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Models\EventLayoutAuditLog;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Services\EventLayoutAuditLogger;
use App\Services\EventLayoutLockService;
use App\Services\EventLayoutReadinessService;
use App\Services\EventLayoutService;
use App\Services\StandardEventLayoutGenerator;
use App\Support\CmartCarbootPhysicalLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 3.5 — Organizer event layout projection and readiness.
 */
class OrganizerEventLayoutController extends Controller
{
    public function __construct(
        private readonly EventLayoutReadinessService $readiness,
        private readonly EventLayoutLockService $locks,
        private readonly EventLayoutAuditLogger $audit,
        private readonly StandardEventLayoutGenerator $standardLayoutGenerator,
        private readonly EventLayoutService $layout,
    ) {}

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

        $existingLabels = $rows->pluck('label')->all();
        $unusedRows = CmartCarbootPhysicalLayout::unusedRowLabels($existingLabels);

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
                'outside_venue_template' => ! CmartCarbootPhysicalLayout::isAllowedRowLabel((string) $row->label),
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

        $activeSiteCount = EventSite::query()
            ->forEvent($carboot_event->id)
            ->active()
            ->count();
        $physicalSiteCount = EventSite::query()
            ->forEvent($carboot_event->id)
            ->count();

        return response()->json([
            'event' => [
                'id' => $carboot_event->id,
                'name' => $carboot_event->title,
                'status' => $carboot_event->status,
                'vendor_site_open_limit' => $carboot_event->vendor_site_open_limit,
                'public_layout_published' => $carboot_event->public_layout_published_at !== null,
                'public_layout_published_at' => $carboot_event->public_layout_published_at?->toIso8601String(),
                'public_layout_entrance_note' => $carboot_event->public_layout_entrance_note,
            ],
            'venue_template' => [
                'key' => CmartCarbootPhysicalLayout::TEMPLATE_KEY,
                'row_labels' => CmartCarbootPhysicalLayout::ROW_LABELS,
                'sites_per_row' => CmartCarbootPhysicalLayout::SITES_PER_ROW,
                'physical_capacity' => CmartCarbootPhysicalLayout::physicalSiteCapacity(),
                'unused_row_labels' => $unusedRows,
                'all_rows_in_use' => $unusedRows === [],
            ],
            'counts' => [
                'physical_sites' => $physicalSiteCount,
                'active_sites' => $activeSiteCount,
                'rows' => $rows->count(),
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

    /**
     * Generate the empty-layout-only standard 4×16 parking template.
     */
    public function generateStandardTemplate(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => 'required|integer|exists:spaces,id',
            'row_categories' => 'required|array',
            'row_categories.A' => 'required|integer|exists:vendor_categories,id',
            'row_categories.B' => 'required|integer|exists:vendor_categories,id',
            'row_categories.C' => 'required|integer|exists:vendor_categories,id',
            'row_categories.D' => 'required|integer|exists:vendor_categories,id',
        ]);

        try {
            $result = $this->standardLayoutGenerator->generate(
                $carboot_event,
                $request->user(),
                $validated,
            );
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => '409 Conflict: '.$exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $error = 'INVALID_STANDARD_TEMPLATE';
            $lower = strtolower($message);
            if (str_contains($lower, 'category')) {
                $error = 'CATEGORY_INACTIVE';
            } elseif (str_contains($lower, 'space')) {
                $error = 'INVALID_SPACE';
            }

            return response()->json([
                'message' => '422 Unprocessable Entity: '.$message,
                'error' => $error,
            ], 422);
        }

        return response()->json([
            'message' => '201 Created: Standard parking layout generated successfully.',
            'template' => CmartCarbootPhysicalLayout::TEMPLATE_KEY,
            'rows_created' => $result['rows_created'],
            'sites_created' => $result['sites_created'],
            'row_labels' => $result['row_labels'],
            'site_labels' => $result['site_labels'],
            'vendor_site_open_limit' => $result['vendor_site_open_limit'],
            'needs_open_site_selection' => $result['needs_open_site_selection'],
            'readiness' => $result['readiness'],
        ], 201);
    }

    /**
     * Confirm exactly vendor_site_open_limit sites as open/active.
     */
    public function setOpenSites(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        $validated = $request->validate([
            'site_ids' => 'required|array|min:1',
            'site_ids.*' => 'required|integer|distinct|exists:event_sites,id',
        ]);

        try {
            $result = $this->layout->setOpenSites(
                $carboot_event,
                $request->user(),
                $validated['site_ids'],
            );
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => '409 Conflict: '.$exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => '422 Unprocessable Entity: '.$exception->getMessage(),
                'error' => 'INVALID_OPEN_SITE_SELECTION',
            ], 422);
        }

        return response()->json([
            'message' => '200 OK: Open sites confirmed successfully.',
            'opened' => $result['opened'],
            'closed' => $result['closed'],
            'readiness' => $result['readiness'],
        ]);
    }

    public function publish(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        $validated = $request->validate([
            'entrance_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $readiness = $this->readiness->assess($carboot_event);

        if (! $readiness['public_ready']) {
            return response()->json([
                'message' => 'The layout is not ready to publish.',
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
                'message' => 'The public layout has been published.',
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
                'message' => 'The public layout has been unpublished.',
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
