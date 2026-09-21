<?php

namespace App\Services;

use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Support\CmartCarbootPhysicalLayout;
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

        if ($event->vendor_site_open_limit === null) {
            $blockers[] = [
                'code' => 'VENDOR_SITE_OPEN_LIMIT_NOT_SET',
                'message' => __('api.choose_the_physical_sites_that_vendors_can_book'),
            ];
        }

        $hasActiveDays = EventDay::query()
            ->where('carboot_event_id', $event->id)
            ->where('operational_status', EventDay::STATUS_ACTIVE)
            ->exists();

        if (! $hasActiveDays) {
            $blockers[] = [
                'code' => 'NO_ACTIVE_EVENT_DAYS',
                'message' => __('api.the_event_has_no_active_operational_days'),
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
                'message' => __('api.the_event_has_no_active_layout_rows'),
            ];
        }

        $outsideTemplate = EventLayoutRow::query()
            ->forEvent($event->id)
            ->get()
            ->filter(fn (EventLayoutRow $row) => ! CmartCarbootPhysicalLayout::isAllowedRowLabel((string) $row->label))
            ->pluck('id')
            ->values()
            ->all();
        if ($outsideTemplate !== []) {
            $blockers[] = [
                'code' => 'ROW_OUTSIDE_VENUE_TEMPLATE',
                'message' => __('api.one_or_more_rows_are_outside_this_venue_s_physical_b13348ca'),
                'row_ids' => $outsideTemplate,
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
                'message' => __('api.one_or_more_active_rows_do_not_have_a_category'),
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
                'message' => __('api.one_or_more_active_rows_reference_an_inactive_or_a_1f828061'),
                'row_ids' => $inactiveCategory,
            ];
        }

        $rowsWithoutSites = $activeRows
            ->filter(function (EventLayoutRow $row) {
                return $row->eventSites->isEmpty();
            })
            ->pluck('id')
            ->values()
            ->all();
        if ($rowsWithoutSites !== []) {
            $blockers[] = [
                'code' => 'ACTIVE_ROW_HAS_NO_ACTIVE_SITES',
                'message' => __('api.one_or_more_active_rows_have_no_physical_sites_del_fe4b22c6'),
                'row_ids' => $rowsWithoutSites,
            ];
        }

        /** @var Collection<int, EventSite> $activeSites */
        $activeSites = EventSite::query()
            ->forEvent($event->id)
            ->active()
            ->with('eventLayoutRow')
            ->get();

        if ($event->vendor_site_open_limit !== null) {
            $limit = (int) $event->vendor_site_open_limit;
            $activeCount = $activeSites->count();
            if ($activeCount < $limit) {
                $blockers[] = [
                    'code' => 'ACTIVE_SITE_COUNT_BELOW_VENDOR_LIMIT',
                    'message' => "Choose booking sites again. {$limit} sites were configured but only {$activeCount} are currently open.",
                ];
            } elseif ($activeCount > $limit) {
                $blockers[] = [
                    'code' => 'ACTIVE_SITE_COUNT_EXCEEDS_VENDOR_LIMIT',
                    'message' => "Choose booking sites again. {$limit} sites were configured but {$activeCount} are currently open.",
                ];
            }
        }

        $missingRow = $activeSites
            ->filter(fn (EventSite $site) => $site->event_layout_row_id === null)
            ->pluck('id')
            ->values()
            ->all();
        if ($missingRow !== []) {
            $blockers[] = [
                'code' => 'ACTIVE_SITE_MISSING_ROW',
                'message' => __('api.one_or_more_active_sites_are_not_linked_to_a_layout_row'),
                'site_ids' => $missingRow,
            ];
            $blockers[] = [
                'code' => 'UNRESOLVED_ACTIVE_SITES',
                'message' => __('api.unresolved_active_legacy_sites_exist_outside_a_layout_row'),
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
                'message' => __('api.one_or_more_active_sites_reference_a_row_belonging_0e36207d'),
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
                'message' => __('api.one_or_more_active_sites_are_missing_a_space_type'),
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
                'message' => __('api.one_or_more_active_sites_have_an_empty_label'),
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
            ->groupBy(fn (EventSite $site) => strtoupper(trim((string) $site->row_label)).':'.$site->position_number)
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->flatten(1)
            ->pluck('id')
            ->values()
            ->all();
        $duplicateIds = array_values(array_unique(array_merge($duplicateLabels, $duplicatePositions)));
        if ($duplicateIds !== []) {
            $blockers[] = [
                'code' => 'DUPLICATE_ACTIVE_SITE_IDENTITY',
                'message' => __('api.duplicate_active_site_labels_or_row_positions_exist'),
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
                'message' => __('api.no_active_public_layout_rows_are_available_for_publication'),
            ];

            return $blockers;
        }

        $orders = $publicRows->pluck('display_order')->all();
        if (count($orders) !== count(array_unique($orders))) {
            $blockers[] = [
                'code' => 'INVALID_PUBLIC_ROW_ORDER',
                'message' => __('api.public_rows_have_duplicate_display_order_values'),
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
                'message' => __('api.one_or_more_public_rows_reference_a_category_that__76099725'),
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
                'message' => __('api.one_or_more_public_rows_have_no_active_visible_sites'),
                'row_ids' => $rowsWithoutVisibleSites,
            ];
        }

        if ($visibleSiteCount === 0) {
            $blockers[] = [
                'code' => 'EMPTY_PUBLIC_LAYOUT',
                'message' => __('api.the_public_layout_projection_is_empty'),
            ];
        }

        return $blockers;
    }
}
