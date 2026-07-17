<?php

namespace App\Services;

use App\Exceptions\AllocationValidationException;
use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\VendorCategory;
use Illuminate\Support\Collection;

/**
 * Phase 2A.8 / 3.7 — vendor-safe read model for physical EventSite availability.
 */
class VendorEventSiteAvailabilityService
{
    public const AVAILABILITY_AVAILABLE = 'available';
    public const AVAILABILITY_OCCUPIED = 'occupied';
    public const AVAILABILITY_UNAVAILABLE = 'unavailable';
    public const AVAILABILITY_DISABLED = 'disabled';

    public function __construct(
        private readonly VendorCategoryResolver $categoryResolver,
    ) {
    }

    /**
     * @throws AllocationValidationException
     */
    public function forEvent(
        CarbootEvent $event,
        ?int $vendorCategoryId = null,
        ?string $legacyCategory = null,
        ?Booking $bookingContext = null,
    ): array {
        if (! $this->isEventBookable($event)) {
            throw new AllocationValidationException(
                'This event is no longer available for booking. Please choose another event.',
                'event_not_bookable',
            );
        }

        $activeDays = EventDay::query()
            ->forEvent((int) $event->id)
            ->active()
            ->ordered()
            ->get();

        if ($activeDays->isEmpty()) {
            throw new AllocationValidationException(
                'This event has no active operational days configured. The Organizer must configure the event schedule before bookings can be accepted.',
                'no_active_event_days',
            );
        }

        $category = $this->resolveCategoryContext(
            $vendorCategoryId,
            $legacyCategory,
            $bookingContext,
        );

        $suggestedCategory = null;
        if ($category === null && $bookingContext === null) {
            // Suggestion only — never silently becomes the requested category.
            $suggestedCategory = null;
        }

        if ($category === null) {
            return $this->emptyCategoryRequiredPayload($event, $activeDays, $suggestedCategory);
        }

        $rows = EventLayoutRow::query()
            ->forEvent((int) $event->id)
            ->active()
            ->where('vendor_category_id', $category->id)
            ->with(['vendorCategory', 'eventSites.space'])
            ->ordered()
            ->get();

        $occupancyBySite = $this->occupancyStatusBySiteForDays($activeDays);

        $rowPayload = [];
        $flatSites = [];
        $excludedIncompatible = 0;

        // Count incompatible active sites for optional summary (vendor-safe).
        $allActiveSites = EventSite::query()
            ->forEvent((int) $event->id)
            ->active()
            ->get();
        foreach ($allActiveSites as $site) {
            if ($site->event_layout_row_id === null) {
                $excludedIncompatible++;
                continue;
            }
        }

        foreach ($rows as $row) {
            $rowCategory = $row->vendorCategory;
            if (! $rowCategory
                || ! $rowCategory->is_active
                || $rowCategory->archived_at !== null
                || (int) $rowCategory->id !== (int) $category->id
            ) {
                continue;
            }

            $siteItems = [];
            foreach ($row->eventSites->sortBy([
                ['display_order', 'asc'],
                ['position_number', 'asc'],
                ['id', 'asc'],
            ]) as $site) {
                if ((int) $site->carboot_event_id !== (int) $event->id) {
                    continue;
                }

                [$status, $selectable] = $this->deriveSiteAvailability($site, $occupancyBySite);

                $item = [
                    'id' => $site->id,
                    'label' => $site->label,
                    'row_label' => $site->row_label,
                    'position_number' => $site->position_number,
                    'grid_row' => $site->grid_row,
                    'grid_column' => $site->grid_column,
                    'display_order' => $site->display_order,
                    'space_id' => $site->space_id,
                    'space_name' => $site->space?->space_size,
                    'price' => number_format((float) ($site->space?->price ?? 0), 2, '.', ''),
                    'operational_status' => $site->operational_status,
                    'availability_status' => $status,
                    'occupancy_status' => $occupancyBySite[(int) $site->id] ?? null,
                    'is_selectable' => $selectable,
                    'event_layout_row_id' => (int) $row->id,
                ];

                $siteItems[] = $item;
                $flatSites[] = $item;
            }

            $rowPayload[] = [
                'id' => (int) $row->id,
                'label' => $row->label,
                'description' => $row->description,
                'display_order' => (int) $row->display_order,
                'category' => $this->categoryResolver->presentCompact($category),
                'sites' => array_values($siteItems),
            ];
        }

        // Sites on other category rows are excluded from selectable results.
        $compatibleRowIds = $rows->pluck('id')->all();
        foreach ($allActiveSites as $site) {
            if ($site->event_layout_row_id !== null
                && ! in_array((int) $site->event_layout_row_id, array_map('intval', $compatibleRowIds), true)
            ) {
                $excludedIncompatible++;
            }
        }

        $readiness = null;
        if ($flatSites === []) {
            $readiness = [
                'status' => 'no_compatible_sites',
                'message' => 'No physical booking sites are available for the selected category yet.',
            ];
        }

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status,
                'day_generation_mode' => $event->day_generation_mode,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
            ],
            'category' => $this->categoryResolver->presentCompact($category),
            'category_required' => false,
            'suggested_category' => $suggestedCategory,
            'excluded_incompatible_site_count' => $excludedIncompatible,
            'operational_days' => $activeDays->map(fn (EventDay $day) => [
                'id' => $day->id,
                'operational_date' => $day->operational_date->format('Y-m-d'),
                'starts_at' => $day->starts_at?->toIso8601String(),
                'ends_at' => $day->ends_at?->toIso8601String(),
                'operational_status' => $day->operational_status,
                'display_order' => $day->display_order,
            ])->values()->all(),
            'selection_rules' => [
                'same_row_required' => true,
                'consecutive_positions_required' => true,
                'same_space_type_required' => true,
                'full_event_duration' => true,
            ],
            'rows' => $rowPayload,
            'sites' => $flatSites,
            'readiness' => $readiness,
        ];
    }

    /**
     * @param  Collection<int, EventDay>  $activeDays
     */
    private function emptyCategoryRequiredPayload(
        CarbootEvent $event,
        Collection $activeDays,
        ?array $suggestedCategory,
    ): array {
        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status,
                'day_generation_mode' => $event->day_generation_mode,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
            ],
            'category' => null,
            'category_required' => true,
            'suggested_category' => $suggestedCategory,
            'excluded_incompatible_site_count' => 0,
            'operational_days' => $activeDays->map(fn (EventDay $day) => [
                'id' => $day->id,
                'operational_date' => $day->operational_date->format('Y-m-d'),
                'starts_at' => $day->starts_at?->toIso8601String(),
                'ends_at' => $day->ends_at?->toIso8601String(),
                'operational_status' => $day->operational_status,
                'display_order' => $day->display_order,
            ])->values()->all(),
            'selection_rules' => [
                'same_row_required' => true,
                'consecutive_positions_required' => true,
                'same_space_type_required' => true,
                'full_event_duration' => true,
            ],
            'rows' => [],
            'sites' => [],
            'readiness' => [
                'status' => 'category_required',
                'message' => 'Sila pilih kategori jualan terlebih dahulu.',
            ],
        ];
    }

    private function resolveCategoryContext(
        ?int $vendorCategoryId,
        ?string $legacyCategory,
        ?Booking $bookingContext,
    ): ?VendorCategory {
        if ($bookingContext !== null) {
            if ($bookingContext->vendor_category_id) {
                return $this->categoryResolver->resolveActiveById((int) $bookingContext->vendor_category_id);
            }

            $fromLegacy = $this->categoryResolver->tryResolveByLabel($bookingContext->product_category);
            if ($fromLegacy) {
                $this->categoryResolver->assertOperationallySelectable($fromLegacy);

                return $fromLegacy;
            }

            throw new AllocationValidationException(
                'Sila pilih kategori jualan terlebih dahulu.',
                'CATEGORY_REQUIRED',
            );
        }

        if ($vendorCategoryId === null && ($legacyCategory === null || trim($legacyCategory) === '')) {
            return null;
        }

        return $this->categoryResolver->resolveForOperationalUse($vendorCategoryId, $legacyCategory);
    }

    /**
     * @param  Collection<int, EventDay>  $activeDays
     * @return array<int, string>
     */
    private function occupancyStatusBySiteForDays(Collection $activeDays): array
    {
        $dayIds = $activeDays->pluck('id')->all();

        if ($dayIds === []) {
            return [];
        }

        return BookingDayAllocation::query()
            ->whereIn('event_day_id', $dayIds)
            ->activeOccupancy()
            ->get(['event_site_id', 'allocation_status'])
            ->groupBy(fn (BookingDayAllocation $allocation) => (int) $allocation->event_site_id)
            ->map(fn (Collection $allocations) => $allocations->contains(
                fn (BookingDayAllocation $allocation) =>
                    $allocation->allocation_status === BookingDayAllocation::STATUS_CONFIRMED,
            )
                ? BookingDayAllocation::STATUS_CONFIRMED
                : BookingDayAllocation::STATUS_RESERVED)
            ->all();
    }

    /**
     * @param  array<int, string>  $occupancyBySite
     * @return array{0: string, 1: bool}
     */
    private function deriveSiteAvailability(EventSite $site, array $occupancyBySite): array
    {
        if ($site->operational_status === EventSite::STATUS_DISABLED) {
            return [self::AVAILABILITY_DISABLED, false];
        }

        if ($site->operational_status === EventSite::STATUS_UNAVAILABLE) {
            return [self::AVAILABILITY_UNAVAILABLE, false];
        }

        if ($site->operational_status !== EventSite::STATUS_ACTIVE) {
            return [self::AVAILABILITY_UNAVAILABLE, false];
        }

        if (! $site->space_id || ! $site->space) {
            return [self::AVAILABILITY_UNAVAILABLE, false];
        }

        if (array_key_exists((int) $site->id, $occupancyBySite)) {
            return [self::AVAILABILITY_OCCUPIED, false];
        }

        return [self::AVAILABILITY_AVAILABLE, true];
    }

    private function isEventBookable(CarbootEvent $event): bool
    {
        if ($event->status === 'Closed') {
            return false;
        }

        return $event->ends_at !== null && $event->ends_at->gte(now());
    }
}
