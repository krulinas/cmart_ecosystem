<?php

namespace App\Services;

use App\Exceptions\AllocationValidationException;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use Illuminate\Support\Collection;

/**
 * Phase 2A.8 — vendor-safe read model for physical EventSite availability.
 */
class VendorEventSiteAvailabilityService
{
    public const AVAILABILITY_AVAILABLE = 'available';
    public const AVAILABILITY_OCCUPIED = 'occupied';
    public const AVAILABILITY_UNAVAILABLE = 'unavailable';
    public const AVAILABILITY_DISABLED = 'disabled';

    /**
     * @throws AllocationValidationException
     */
    public function forEvent(CarbootEvent $event): array
    {
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

        $sites = EventSite::query()
            ->forEvent((int) $event->id)
            ->with('space')
            ->orderedForLayout()
            ->get();

        $occupiedSiteIds = $this->occupiedSiteIdsForDays($activeDays);

        $sitePayload = $sites->map(function (EventSite $site) use ($occupiedSiteIds) {
            [$status, $selectable] = $this->deriveSiteAvailability($site, $occupiedSiteIds);

            return [
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
                'is_selectable' => $selectable,
            ];
        })->values()->all();

        $readiness = null;
        if ($sites->isEmpty()) {
            $readiness = [
                'status' => 'no_event_sites',
                'message' => 'No physical booking sites have been configured for this event yet.',
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
            'sites' => $sitePayload,
            'readiness' => $readiness,
        ];
    }

    /**
     * @param  Collection<int, EventDay>  $activeDays
     * @return list<int>
     */
    private function occupiedSiteIdsForDays(Collection $activeDays): array
    {
        $dayIds = $activeDays->pluck('id')->all();

        if ($dayIds === []) {
            return [];
        }

        return BookingDayAllocation::query()
            ->whereIn('event_day_id', $dayIds)
            ->activeOccupancy()
            ->distinct()
            ->pluck('event_site_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $occupiedSiteIds
     * @return array{0: string, 1: bool}
     */
    private function deriveSiteAvailability(EventSite $site, array $occupiedSiteIds): array
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

        if (in_array((int) $site->id, $occupiedSiteIds, true)) {
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
