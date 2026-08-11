<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCategoryOverride;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\VendorCategory;

/**
 * Phase 3.8 — Organizer category placement inspection and options.
 */
class OrganizerBookingCategoryPlacementService
{
    public function __construct(
        private readonly OrganizerBookingReassignmentEligibilityService $eligibility,
        private readonly OrganizerBookingAssignmentFingerprintService $fingerprint,
    ) {
    }

    public function placementPayload(Booking $booking): array
    {
        $booking->loadMissing([
            'vendorCategory',
            'invoice',
            'activeCategoryOverride.appliedBy',
            'bookingDayAllocations.eventSite.space',
            'bookingDayAllocations.eventSite.eventLayoutRow.vendorCategory',
            'bookingDayAllocations.eventDay',
        ]);

        $blockers = $this->eligibility->blockingReasons($booking);
        $requirements = $this->safeRequirements($booking);
        $assignment = $this->currentAssignment($booking);
        $bookingCategory = $this->bookingCategoryBlock($booking);
        $compatible = $assignment['compatible'] ?? false;

        $activeOverride = $booking->activeCategoryOverride;
        $overrideBlock = [
            'required' => ! $compatible && $assignment['site_count'] > 0,
            'active' => $activeOverride !== null,
            'reason' => $activeOverride?->reason,
            'applied_at' => $activeOverride?->applied_at?->toIso8601String(),
            'applied_by' => $activeOverride?->appliedBy ? [
                'id' => $activeOverride->appliedBy->id,
                'name' => $activeOverride->appliedBy->name,
            ] : null,
            'history_count' => $booking->categoryOverrides()->count(),
        ];

        return [
            'booking' => [
                'id' => $booking->id,
                'status' => $booking->approval_status,
            ],
            'booking_category' => $bookingCategory,
            'current_assignment' => $assignment,
            'override' => $overrideBlock,
            'reassignment' => [
                'allowed' => $blockers === [],
                'blocking_reasons' => $blockers,
                'required_site_count' => $requirements['site_count'] ?? 0,
                'required_space_id' => $requirements['space_id'] ?? null,
                'assignment_fingerprint' => $this->fingerprint->compute($booking),
            ],
        ];
    }

    public function optionsPayload(Booking $booking): array
    {
        $booking->loadMissing(['vendorCategory', 'invoice']);
        $this->eligibility->assertEligible($booking);

        $requirements = $this->eligibility->requiredSpaceFromBooking($booking);
        $bookingCategory = $this->bookingCategoryBlock($booking);
        $retainedDayIds = $this->eligibility->retainedEventDayIds($booking);
        $currentSiteIds = $this->eligibility->currentSiteIds($booking);

        $occupied = \App\Models\BookingDayAllocation::query()
            ->whereIn('event_day_id', $retainedDayIds)
            ->activeOccupancy()
            ->where('booking_id', '!=', $booking->id)
            ->pluck('event_site_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $rows = EventLayoutRow::query()
            ->forEvent((int) $booking->carboot_event_id)
            ->active()
            ->with(['vendorCategory', 'eventSites.space'])
            ->ordered()
            ->get();

        $rowPayload = [];
        foreach ($rows as $row) {
            $rowCategory = $row->vendorCategory;
            if (! $rowCategory || ! $rowCategory->is_active || $rowCategory->archived_at !== null) {
                continue;
            }

            $compatible = (int) $booking->vendor_category_id === (int) $row->vendor_category_id;
            $sites = [];
            foreach ($row->eventSites->sortBy([['display_order', 'asc'], ['position_number', 'asc']]) as $site) {
                if ($site->operational_status !== EventSite::STATUS_ACTIVE) {
                    continue;
                }
                if ((int) $site->space_id !== $requirements['space_id']) {
                    continue;
                }

                $owned = in_array((int) $site->id, $currentSiteIds, true);
                $occupiedElsewhere = in_array((int) $site->id, $occupied, true);
                $selectable = $owned || ! $occupiedElsewhere;

                $sites[] = [
                    'id' => $site->id,
                    'label' => $site->label,
                    'position_number' => $site->position_number,
                    'is_owned_by_booking' => $owned,
                    'is_selectable' => $selectable,
                    'availability_status' => $selectable ? 'available' : 'occupied',
                ];
            }

            if ($sites === []) {
                continue;
            }

            $rowPayload[] = [
                'id' => (int) $row->id,
                'label' => $row->label,
                'category' => [
                    'id' => (int) $rowCategory->id,
                    'slug' => $rowCategory->slug,
                    'label' => $rowCategory->label,
                ],
                'category_compatible' => $compatible,
                'override_required' => ! $compatible,
                'sites' => $sites,
            ];
        }

        return [
            'booking_category' => $bookingCategory,
            'requirements' => [
                'site_count' => $requirements['site_count'],
                'space_id' => $requirements['space_id'],
                'space_label' => $requirements['space_label'],
                'unit_price' => $requirements['unit_price'],
                'assignment_fingerprint' => $this->fingerprint->compute($booking),
            ],
            'rows' => $rowPayload,
        ];
    }

    private function bookingCategoryBlock(Booking $booking): array
    {
        $label = $booking->category_label_snapshot ?? $booking->product_category ?? '';
        $category = $booking->vendorCategory;

        return [
            'id' => (int) ($booking->vendor_category_id ?? 0),
            'slug' => $category?->slug ?? '',
            'label' => $category?->label ?? $label,
            'snapshot' => $label,
        ];
    }

    private function currentAssignment(Booking $booking): array
    {
        $active = $this->eligibility->activeAllocations($booking);
        $sites = $active->pluck('eventSite')->filter()->unique('id')->sortBy('label')->values();
        $rows = $sites->map(fn (EventSite $site) => $site->eventLayoutRow)->filter()->unique('id')->values();

        $rowPayload = $rows->map(function (EventLayoutRow $row) {
            $cat = $row->vendorCategory;

            return [
                'id' => (int) $row->id,
                'label' => $row->label,
                'category' => $cat ? [
                    'id' => (int) $cat->id,
                    'slug' => $cat->slug,
                    'label' => $cat->label,
                ] : null,
            ];
        })->values()->all();

        $firstSite = $sites->first();
        $space = $firstSite?->space;
        $assignedCategoryIds = $rows->pluck('vendor_category_id')->filter()->unique()->values();
        $compatible = $assignedCategoryIds->count() === 1
            && (int) $booking->vendor_category_id === (int) $assignedCategoryIds->first();

        return [
            'site_count' => $sites->count(),
            'space' => $space ? [
                'id' => (int) $space->id,
                'name' => $space->space_size,
            ] : null,
            'rows' => $rowPayload,
            'sites' => $sites->map(fn (EventSite $site) => [
                'id' => $site->id,
                'label' => $site->label,
            ])->values()->all(),
            'compatible' => $compatible && $sites->isNotEmpty(),
        ];
    }

    /**
     * @return array{site_count?: int, space_id?: int, space_label?: string, unit_price?: string}
     */
    private function safeRequirements(Booking $booking): array
    {
        try {
            return $this->eligibility->requiredSpaceFromBooking($booking);
        } catch (\Throwable) {
            return [];
        }
    }
}
