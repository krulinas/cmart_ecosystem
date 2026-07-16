<?php

namespace App\Services;

use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use Illuminate\Support\Collection;

/**
 * Phase 3.5 — operational and public-layout readiness for Organizer events.
 */
class EventLayoutReadinessService
{
    /**
     * @return array{
     *   operational_ready: bool,
     *   public_ready: bool,
     *   blocking_reasons: list<array{code: string, message: string, row_ids?: list<int>, site_ids?: list<int>}>
     * }
     */
    public function assess(CarbootEvent $event): array
    {
        $operational = $this->operationalBlockers($event);
        $public = $this->publicBlockers($event, $operational === []);

        $blocking = array_merge($operational, $public);

        return [
            'operational_ready' => $operational === [],
            'public_ready' => $operational === [] && $public === [],
            'blocking_reasons' => $blocking,
        ];
    }

    /**
     * @return list<array{code: string, message: string, row_ids?: list<int>, site_ids?: list<int>}>
     */
    public function operationalBlockers(CarbootEvent $event): array
    {
        $blockers = [];

        $hasActiveDays = EventDay::query()
            ->where('carboot_event_id', $event->id)
            ->where('operational_status', EventDay::STATUS_ACTIVE)
            ->exists();

        if (! $hasActiveDays) {
            $blockers[] = [
                'code' => 'NO_ACTIVE_EVENT_DAYS',
                'message' => 'The event has no active operational days.',
            ];
        }

        /** @var Collection<int, EventLayoutRow> $activeRows */
        $activeRows = EventLayoutRow::query()
            ->forEvent($event->id)
            ->active()
            ->with(['vendorCategory', 'eventSites'])
            ->ordered()
            ->get();

        if ($activeRows->isEmpty()) {
            $blockers[] = [
                'code' => 'NO_ACTIVE_LAYOUT_ROWS',
                'message' => 'The event has no active layout rows.',
            ];
        }

        $missingCategory = $activeRows
            ->filter(fn (EventLayoutRow $row) => $row->vendor_category_id === null)
            ->pluck('id')
            ->values()
            ->all();
        if ($missingCategory !== []) {
            $blockers[] = [
                'code' => 'ACTIVE_ROW_MISSING_CATEGORY',
                'message' => 'One or more active rows do not have a category.',
                'row_ids' => $missingCategory,
            ];
        }

        $inactiveCategory = $activeRows
            ->filter(function (EventLayoutRow $row) {
                $category = $row->vendorCategory;
                if ($row->vendor_category_id === null) {
                    return false;
                }

                return ! $category
                    || ! $category->is_active
                    || $category->archived_at !== null;
            })
            ->pluck('id')
            ->values()
            ->all();
        if ($inactiveCategory !== []) {
            $blockers[] = [
                'code' => 'ROW_CATEGORY_INACTIVE',
                'message' => 'One or more active rows reference an inactive or archived category.',
                'row_ids' => $inactiveCategory,
            ];
        }

        $rowsWithoutSites = $activeRows
            ->filter(function (EventLayoutRow $row) {
                return $row->eventSites
                    ->where('operational_status', EventSite::STATUS_ACTIVE)
                    ->isEmpty();
            })
            ->pluck('id')
            ->values()
            ->all();
        if ($rowsWithoutSites !== []) {
            $blockers[] = [
                'code' => 'ACTIVE_ROW_HAS_NO_ACTIVE_SITES',
                'message' => 'One or more active rows have no active sites.',
                'row_ids' => $rowsWithoutSites,
            ];
        }

        /** @var Collection<int, EventSite> $activeSites */
        $activeSites = EventSite::query()
            ->forEvent($event->id)
            ->active()
            ->with('eventLayoutRow')
            ->get();

        $missingRow = $activeSites
            ->filter(fn (EventSite $site) => $site->event_layout_row_id === null)
            ->pluck('id')
            ->values()
            ->all();
        if ($missingRow !== []) {
            $blockers[] = [
                'code' => 'ACTIVE_SITE_MISSING_ROW',
                'message' => 'One or more active sites are not linked to a layout row.',
                'site_ids' => $missingRow,
            ];
            $blockers[] = [
                'code' => 'UNRESOLVED_ACTIVE_SITES',
                'message' => 'Unresolved active legacy sites exist outside a layout row.',
                'site_ids' => $missingRow,
            ];
        }

        $mismatch = $activeSites
            ->filter(function (EventSite $site) use ($event) {
                if ($site->event_layout_row_id === null) {
                    return false;
                }
                $row = $site->eventLayoutRow;

                return ! $row || (int) $row->carboot_event_id !== (int) $event->id;
            })
            ->pluck('id')
            ->values()
            ->all();
        if ($mismatch !== []) {
            $blockers[] = [
                'code' => 'SITE_EVENT_ROW_MISMATCH',
                'message' => 'One or more active sites reference a row belonging to a different event.',
                'site_ids' => $mismatch,
            ];
        }

        $missingSpace = $activeSites
            ->filter(fn (EventSite $site) => $site->space_id === null)
            ->pluck('id')
            ->values()
            ->all();
        if ($missingSpace !== []) {
            $blockers[] = [
                'code' => 'ACTIVE_SITE_MISSING_SPACE',
                'message' => 'One or more active sites are missing a space type.',
                'site_ids' => $missingSpace,
            ];
        }

        $invalidLabel = $activeSites
            ->filter(fn (EventSite $site) => trim((string) $site->label) === '')
            ->pluck('id')
            ->values()
            ->all();
        if ($invalidLabel !== []) {
            $blockers[] = [
                'code' => 'ACTIVE_SITE_INVALID_LABEL',
                'message' => 'One or more active sites have an empty label.',
                'site_ids' => $invalidLabel,
            ];
        }

        $duplicateLabels = $activeSites
            ->groupBy(fn (EventSite $site) => strtoupper(trim((string) $site->label)))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->flatten(1)
            ->pluck('id')
            ->values()
            ->all();
        $duplicatePositions = $activeSites
            ->groupBy(fn (EventSite $site) => strtoupper(trim((string) $site->row_label)) . ':' . $site->position_number)
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->flatten(1)
            ->pluck('id')
            ->values()
            ->all();
        $duplicateIds = array_values(array_unique(array_merge($duplicateLabels, $duplicatePositions)));
        if ($duplicateIds !== []) {
            $blockers[] = [
                'code' => 'DUPLICATE_ACTIVE_SITE_IDENTITY',
                'message' => 'Duplicate active site labels or row positions exist.',
                'site_ids' => $duplicateIds,
            ];
        }

        return $blockers;
    }

    /**
     * @return list<array{code: string, message: string, row_ids?: list<int>, site_ids?: list<int>}>
     */
    public function publicBlockers(CarbootEvent $event, bool $operationalReady): array
    {
        if (! $operationalReady) {
            return [];
        }

        $blockers = [];

        /** @var Collection<int, EventLayoutRow> $publicRows */
        $publicRows = EventLayoutRow::query()
            ->forEvent($event->id)
            ->active()
            ->where('is_public', true)
            ->with(['vendorCategory', 'eventSites'])
            ->ordered()
            ->get();

        if ($publicRows->isEmpty()) {
            $blockers[] = [
                'code' => 'NO_PUBLIC_ROWS',
                'message' => 'No active public layout rows are available for publication.',
            ];

            return $blockers;
        }

        $orders = $publicRows->pluck('display_order')->all();
        if (count($orders) !== count(array_unique($orders))) {
            $blockers[] = [
                'code' => 'INVALID_PUBLIC_ROW_ORDER',
                'message' => 'Public rows have duplicate display_order values.',
                'row_ids' => $publicRows->pluck('id')->all(),
            ];
        }

        $nonPublicCategory = $publicRows
            ->filter(function (EventLayoutRow $row) {
                $category = $row->vendorCategory;

                return ! $category
                    || ! $category->is_active
                    || $category->archived_at !== null
                    || ! $category->is_public;
            })
            ->pluck('id')
            ->values()
            ->all();
        if ($nonPublicCategory !== []) {
            $blockers[] = [
                'code' => 'PUBLIC_ROW_CATEGORY_NOT_PUBLIC',
                'message' => 'One or more public rows reference a category that is not active and public.',
                'row_ids' => $nonPublicCategory,
            ];
        }

        $rowsWithoutVisibleSites = [];
        $visibleSiteCount = 0;
        foreach ($publicRows as $row) {
            $visible = $row->eventSites
                ->where('operational_status', EventSite::STATUS_ACTIVE)
                ->count();
            $visibleSiteCount += $visible;
            if ($visible === 0) {
                $rowsWithoutVisibleSites[] = $row->id;
            }
        }

        if ($rowsWithoutVisibleSites !== []) {
            $blockers[] = [
                'code' => 'PUBLIC_ROW_HAS_NO_VISIBLE_SITES',
                'message' => 'One or more public rows have no active visible sites.',
                'row_ids' => $rowsWithoutVisibleSites,
            ];
        }

        if ($visibleSiteCount === 0) {
            $blockers[] = [
                'code' => 'EMPTY_PUBLIC_LAYOUT',
                'message' => 'The public layout projection is empty.',
            ];
        }

        return $blockers;
    }
}
