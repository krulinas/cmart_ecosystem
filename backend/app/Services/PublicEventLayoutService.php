<?php

namespace App\Services;

use App\Models\CarbootEvent;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use Illuminate\Support\Collection;

/**
 * Phase 3.10 — allowlisted public navigation projection.
 *
 * This service never reads bookings, allocations, invoices, overrides, audits,
 * or Organizer lock state.
 */
class PublicEventLayoutService
{
    public function __construct(
        private readonly EventLayoutReadinessService $readiness,
    ) {
    }

    public function present(CarbootEvent $event): ?array
    {
        if ($event->public_layout_published_at === null) {
            return null;
        }

        $historical = $event->status === 'Closed'
            || ($event->ends_at !== null && $event->ends_at->lt(now()));

        if (! $historical && ! $this->readiness->assess($event)['public_ready']) {
            return null;
        }

        $rows = EventLayoutRow::query()
            ->forEvent((int) $event->id)
            ->active()
            ->where('is_public', true)
            ->whereHas('vendorCategory', fn ($query) => $query
                ->where('is_active', true)
                ->where('is_public', true)
                ->whereNull('archived_at'))
            ->with([
                'vendorCategory',
                'eventSites' => fn ($query) => $query
                    ->where('carboot_event_id', $event->id)
                    ->where('operational_status', EventSite::STATUS_ACTIVE)
                    ->with('space')
                    ->orderBy('display_order')
                    ->orderBy('position_number')
                    ->orderBy('id'),
            ])
            ->ordered()
            ->get()
            ->filter(fn (EventLayoutRow $row) => $row->eventSites->isNotEmpty())
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $rowPayload = $rows->map(fn (EventLayoutRow $row) => [
            'id' => (int) $row->id,
            'label' => $row->label,
            'description' => $row->description,
            'display_order' => (int) $row->display_order,
            'category' => [
                'id' => (int) $row->vendorCategory->id,
                'slug' => $row->vendorCategory->slug,
                'label' => $row->vendorCategory->label,
                'description' => $row->vendorCategory->description,
            ],
            'site_count' => $row->eventSites->count(),
            'sites' => $row->eventSites->map(fn (EventSite $site) => [
                'id' => (int) $site->id,
                'label' => $site->label,
                'display_order' => (int) $site->display_order,
                'position_number' => (int) $site->position_number,
                'grid_row' => $site->grid_row !== null ? (int) $site->grid_row : null,
                'grid_column' => $site->grid_column !== null ? (int) $site->grid_column : null,
                'space' => $site->space
                    ? ['name' => $site->space->space_size]
                    : null,
            ])->values()->all(),
        ])->values();

        return [
            'layout_available' => true,
            'published' => true,
            'historical' => $historical,
            'event' => [
                'id' => (int) $event->id,
                'name' => $event->title,
                'status' => $event->status,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
            ],
            'entrance_note' => $event->public_layout_entrance_note,
            'categories' => $this->presentCategories($rows),
            'rows' => $rowPayload->all(),
        ];
    }

    /**
     * @param  Collection<int, EventLayoutRow>  $rows
     * @return list<array{id: int, slug: string, label: string, description: ?string, display_order: int, row_count: int, site_count: int}>
     */
    private function presentCategories(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (EventLayoutRow $row) => (int) $row->vendor_category_id)
            ->map(function (Collection $categoryRows) {
                /** @var EventLayoutRow $first */
                $first = $categoryRows->first();
                $category = $first->vendorCategory;

                return [
                    'id' => (int) $category->id,
                    'slug' => $category->slug,
                    'label' => $category->label,
                    'description' => $category->description,
                    'display_order' => (int) $category->display_order,
                    'row_count' => $categoryRows->count(),
                    'site_count' => $categoryRows->sum(
                        fn (EventLayoutRow $row) => $row->eventSites->count(),
                    ),
                ];
            })
            ->sortBy([
                ['display_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();
    }
}
