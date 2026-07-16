<?php

namespace App\Services;

use App\Models\BookingDayAllocation;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.5 — structural lock inspection for Organizer layout mutations.
 */
class EventLayoutLockService
{
    /**
     * @return array{
     *   has_active_allocations: bool,
     *   has_allocation_history: bool,
     *   structural_replacement_locked: bool
     * }
     */
    public function eventLockSummary(int $eventId): array
    {
        $hasHistory = BookingDayAllocation::query()
            ->whereHas('eventSite', fn ($q) => $q->where('carboot_event_id', $eventId))
            ->exists();

        $hasActive = BookingDayAllocation::query()
            ->activeOccupancy()
            ->whereHas('eventSite', fn ($q) => $q->where('carboot_event_id', $eventId))
            ->exists();

        return [
            'has_active_allocations' => $hasActive,
            'has_allocation_history' => $hasHistory,
            'structural_replacement_locked' => $hasHistory,
        ];
    }

    /**
     * @return array{
     *   rename_locked: bool,
     *   category_change_locked: bool,
     *   delete_locked: bool,
     *   archive_locked: bool,
     *   has_active_allocations: bool,
     *   has_allocation_history: bool,
     *   site_count: int
     * }
     */
    public function rowLocks(EventLayoutRow $row): array
    {
        $siteIds = EventSite::query()
            ->where('event_layout_row_id', $row->id)
            ->pluck('id');

        $siteCount = $siteIds->count();
        $hasHistory = $siteCount > 0 && BookingDayAllocation::query()
            ->whereIn('event_site_id', $siteIds)
            ->exists();
        $hasActive = $siteCount > 0 && BookingDayAllocation::query()
            ->activeOccupancy()
            ->whereIn('event_site_id', $siteIds)
            ->exists();

        return [
            'rename_locked' => $hasHistory,
            'category_change_locked' => $hasHistory,
            'delete_locked' => $siteCount > 0 || $hasHistory,
            'archive_locked' => $hasActive,
            'has_active_allocations' => $hasActive,
            'has_allocation_history' => $hasHistory,
            'site_count' => $siteCount,
        ];
    }

    /**
     * @return array{
     *   structure_locked: bool,
     *   disable_locked: bool,
     *   delete_locked: bool,
     *   has_active_allocations: bool,
     *   has_allocation_history: bool
     * }
     */
    public function siteLocks(EventSite $site): array
    {
        $hasHistory = $site->hasAllocationHistory();
        $hasActive = BookingDayAllocation::query()
            ->activeOccupancy()
            ->where('event_site_id', $site->id)
            ->exists();

        return [
            'structure_locked' => $hasHistory,
            'disable_locked' => $hasActive,
            'delete_locked' => $hasHistory,
            'has_active_allocations' => $hasActive,
            'has_allocation_history' => $hasHistory,
        ];
    }

    public function rowHasAllocationHistory(EventLayoutRow $row): bool
    {
        return $this->rowLocks($row)['has_allocation_history'];
    }

    public function rowHasActiveAllocations(EventLayoutRow $row): bool
    {
        return $this->rowLocks($row)['has_active_allocations'];
    }

    public function siteHasActiveAllocations(EventSite $site): bool
    {
        return BookingDayAllocation::query()
            ->activeOccupancy()
            ->where('event_site_id', $site->id)
            ->exists();
    }

    /**
     * Occupancy summary for Organizer layout projection (no booking PII).
     *
     * @return 'available'|'reserved'|'confirmed'|'released-history'
     */
    public function siteOccupancySummary(EventSite $site): string
    {
        $statuses = BookingDayAllocation::query()
            ->where('event_site_id', $site->id)
            ->pluck('allocation_status');

        if ($statuses->contains(BookingDayAllocation::STATUS_CONFIRMED)) {
            return 'confirmed';
        }
        if ($statuses->contains(BookingDayAllocation::STATUS_RESERVED)) {
            return 'reserved';
        }
        if ($statuses->isNotEmpty()) {
            return 'released-history';
        }

        return 'available';
    }

    /**
     * Batch occupancy for many sites (avoids N+1 on layout read).
     *
     * @param  list<int>  $siteIds
     * @return array<int, string>
     */
    public function occupancyBySiteIds(array $siteIds): array
    {
        if ($siteIds === []) {
            return [];
        }

        $rows = DB::table('booking_day_allocations')
            ->select('event_site_id', 'allocation_status')
            ->whereIn('event_site_id', $siteIds)
            ->get()
            ->groupBy('event_site_id');

        $result = [];
        foreach ($siteIds as $siteId) {
            $statuses = ($rows[$siteId] ?? collect())->pluck('allocation_status');
            if ($statuses->contains(BookingDayAllocation::STATUS_CONFIRMED)) {
                $result[$siteId] = 'confirmed';
            } elseif ($statuses->contains(BookingDayAllocation::STATUS_RESERVED)) {
                $result[$siteId] = 'reserved';
            } elseif ($statuses->isNotEmpty()) {
                $result[$siteId] = 'released-history';
            } else {
                $result[$siteId] = 'available';
            }
        }

        return $result;
    }
}
