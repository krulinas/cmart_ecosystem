<?php

namespace App\Services;

use App\Exceptions\AllocationValidationException;
use App\Exceptions\DomainConflictException;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\VendorCategory;
use Illuminate\Support\Collection;

/**
 * Phase 3.7 — authoritative site ↔ row category compatibility checks.
 */
class BookingSiteCategoryValidator
{
    public function __construct(
        private readonly EventLayoutReadinessService $readinessService,
    ) {
    }

    /**
     * @throws DomainConflictException
     */
    public function assertEventOperationallyLayoutReady(\App\Models\CarbootEvent $event): void
    {
        $assessment = $this->readinessService->assess($event);

        if ($assessment['operational_ready']) {
            return;
        }

        $messages = collect($assessment['blocking_reasons'])
            ->pluck('message')
            ->filter()
            ->unique()
            ->values()
            ->all();

        throw new DomainConflictException(
            $messages !== []
                ? implode(' ', $messages)
                : 'The event layout is not ready for vendor booking yet.',
            'EVENT_LAYOUT_NOT_READY',
        );
    }

    /**
     * Validate locked EventSite models against the booking category.
     *
     * @param  Collection<int, EventSite>  $sites
     *
     * @throws AllocationValidationException
     * @throws DomainConflictException
     */
    public function assertSitesCompatibleWithCategory(
        Collection $sites,
        VendorCategory $category,
        int $eventId,
    ): void {
        if ($sites->isEmpty()) {
            throw new AllocationValidationException(
                'One or more event sites do not exist.',
                'missing_event_site',
            );
        }

        $rowIds = $sites
            ->pluck('event_layout_row_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, EventLayoutRow> $rows */
        $rows = EventLayoutRow::query()
            ->whereIn('id', $rowIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $categoryIdsSeen = [];

        foreach ($sites as $site) {
            if ((int) $site->carboot_event_id !== $eventId) {
                throw new AllocationValidationException(
                    'One or more event sites do not belong to this event.',
                    'site_event_mismatch',
                );
            }

            if ($site->event_layout_row_id === null) {
                throw new AllocationValidationException(
                    'One or more selected sites are missing a layout row.',
                    'SITE_MISSING_LAYOUT_ROW',
                );
            }

            $row = $rows->get((int) $site->event_layout_row_id);
            if (! $row) {
                throw new AllocationValidationException(
                    'One or more selected sites reference a missing layout row.',
                    'SITE_MISSING_LAYOUT_ROW',
                );
            }

            if ((int) $row->carboot_event_id !== $eventId) {
                throw new DomainConflictException(
                    'The event layout has changed. Please review and select sites again.',
                    'LAYOUT_CHANGED',
                );
            }

            if (! $row->is_active || $row->archived_at !== null) {
                throw new AllocationValidationException(
                    'One or more selected sites belong to an inactive layout row.',
                    'SITE_ROW_INACTIVE',
                );
            }

            if ($row->vendor_category_id === null) {
                throw new AllocationValidationException(
                    'One or more selected sites belong to a row without a category.',
                    'SITE_ROW_CATEGORY_MISSING',
                );
            }

            $categoryIdsSeen[(int) $row->vendor_category_id] = true;

            if ((int) $row->vendor_category_id !== (int) $category->id) {
                throw new AllocationValidationException(
                    'The selected sites do not match your selling category.',
                    'SITE_CATEGORY_INCOMPATIBLE',
                );
            }
        }

        if (count($categoryIdsSeen) > 1) {
            throw new AllocationValidationException(
                'The selected sites do not match your selling category.',
                'MIXED_CATEGORY_SITE_SELECTION',
            );
        }
    }

    /**
     * Validate retained allocation sites when changing booking category.
     *
     * @param  list<int>  $eventSiteIds
     *
     * @throws AllocationValidationException
     * @throws DomainConflictException
     */
    public function assertSiteIdsCompatibleWithCategory(
        array $eventSiteIds,
        VendorCategory $category,
        int $eventId,
    ): void {
        $ids = collect($eventSiteIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $sites = EventSite::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($sites->count() !== count($ids)) {
            throw new AllocationValidationException(
                'One or more event sites do not exist.',
                'missing_event_site',
            );
        }

        $this->assertSitesCompatibleWithCategory($sites, $category, $eventId);
    }
}
