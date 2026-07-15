<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAttendanceException;
use App\Models\BookingAttendanceExceptionDay;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2B.4 — read-only Organizer released-day recovery queue.
 *
 * Derives partial EventDay/EventSite slices from historical organizer_day_exception
 * releases where the source booking still retains active allocations on other days.
 */
class OrganizerReleasedDayRecoveryService
{
    public const RECOVERY_RECOVERABLE = 'recoverable';

    public const RECOVERY_PARTIALLY_BLOCKED = 'partially_blocked';

    public const RECOVERY_FULLY_BLOCKED = 'fully_blocked';

    public const RECOVERY_EXPIRED = 'expired';

    public const RECOVERY_OPERATIONALLY_UNAVAILABLE = 'operationally_unavailable';

    public const RECOVERY_STATES = [
        self::RECOVERY_RECOVERABLE,
        self::RECOVERY_PARTIALLY_BLOCKED,
        self::RECOVERY_FULLY_BLOCKED,
        self::RECOVERY_EXPIRED,
        self::RECOVERY_OPERATIONALLY_UNAVAILABLE,
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginated(array $filters): LengthAwarePaginator
    {
        $groups = $this->buildGroups($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 5), 100);
        $total = $groups->count();
        $slice = $groups->forPage($page, $perPage)->values();

        return new Paginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildGroups(array $filters): Collection
    {
        $releasedRows = $this->partialExceptionReleaseQuery($filters)
            ->with([
                'booking.invoice',
                'booking.user.businessProfile',
                'booking.carbootEvent',
                'eventDay.carbootEvent',
                'eventSite.space',
                'releasedBy',
            ])
            ->orderBy(
                EventDay::query()
                    ->select('starts_at')
                    ->whereColumn('event_days.id', 'booking_day_allocations.event_day_id')
                    ->limit(1),
            )
            ->orderByDesc('released_at')
            ->orderBy('booking_id')
            ->get();

        if ($releasedRows->isEmpty()) {
            return collect();
        }

        $grouped = $releasedRows->groupBy(function (BookingDayAllocation $row) {
            return implode(':', [
                $row->booking_id,
                $row->event_day_id,
                $row->release_reason,
                $row->released_by,
                $row->released_at?->format('Y-m-d H:i:s') ?? 'unknown',
            ]);
        });

        $eventIds = $releasedRows
            ->pluck('eventDay.carboot_event_id')
            ->filter()
            ->unique()
            ->values();
        $activeDaysByEvent = $this->activeDaysByEvent($eventIds);
        $occupiedSiteIdsByEvent = $this->occupiedSiteIdsByEvent($activeDaysByEvent);
        $conflicts = $this->activeConflictsForRows($releasedRows);
        $exceptionReasons = $this->exceptionReasonsForGroups($grouped);

        $items = $grouped->map(function (Collection $rows, string $groupKey) use (
            $activeDaysByEvent,
            $occupiedSiteIdsByEvent,
            $conflicts,
            $exceptionReasons,
        ) {
            /** @var BookingDayAllocation $first */
            $first = $rows->sortBy('id')->first();
            $booking = $first->booking;
            $eventDay = $first->eventDay;
            $event = $eventDay?->carbootEvent ?? $booking?->carbootEvent;
            $eventId = (int) ($event?->id ?? 0);

            $siteStates = $rows
                ->sortBy(fn (BookingDayAllocation $row) => $row->eventSite?->label ?? '')
                ->map(function (BookingDayAllocation $row) use ($eventDay, $event, $conflicts) {
                    return $this->deriveSiteRecovery($row, $eventDay, $event, $conflicts);
                })
                ->values();

            $recoveryState = $this->deriveGroupRecoveryState($eventDay, $event, $siteStates);
            $siteIds = $rows->pluck('event_site_id')->map(fn ($id) => (int) $id)->unique()->values();
            $occupiedForEvent = $occupiedSiteIdsByEvent[$eventId] ?? [];
            $standardFullEventAvailable = $siteIds->every(
                fn (int $siteId) => ! in_array($siteId, $occupiedForEvent, true),
            );

            $recoverableCount = $siteStates->where('recovery_state', self::RECOVERY_RECOVERABLE)->count();
            $blockedCount = $siteStates->filter(fn (array $site) =>
                $site['recovery_state'] === self::RECOVERY_FULLY_BLOCKED
                && ($site['blocker'] ?? '') === 'Occupied by another active booking',
            )->count();

            $reasonKey = $first->booking_id . ':' . $first->event_day_id;

            return [
                'group_key' => $groupKey,
                'id' => sprintf('booking-%d-day-%d', $first->booking_id, $first->event_day_id),
                'source_booking' => $booking,
                'event' => $event,
                'event_day' => $eventDay,
                'released_rows' => $rows,
                'released_sites' => $siteStates,
                'release' => [
                    'reason' => $first->release_reason,
                    'released_at' => $first->released_at,
                    'released_by' => $first->releasedBy,
                ],
                'attendance_exception_reason' => $exceptionReasons[$reasonKey] ?? null,
                'source_payment_state' => VendorBookingPresenter::withdrawalPaymentState($booking),
                'source_invoice_amount' => $booking->invoice
                    ? number_format((float) $booking->invoice->amount, 2, '.', '')
                    : null,
                'recovery_state' => $recoveryState,
                'recoverable_site_count' => $recoverableCount,
                'blocked_site_count' => $blockedCount,
                'standard_full_event_available' => $standardFullEventAvailable,
                'recovery_channel' => 'released_day_queue',
            ];
        })->values();

        if (! empty($filters['recovery_state'])) {
            $items = $items->where('recovery_state', $filters['recovery_state'])->values();
        }

        return $items->sortBy([
            fn (array $item) => $item['event_day']?->starts_at?->timestamp ?? PHP_INT_MAX,
            fn (array $item) => -1 * ($item['release']['released_at']?->timestamp ?? 0),
            fn (array $item) => $item['source_booking']?->id ?? 0,
        ])->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function partialExceptionReleaseQuery(array $filters): Builder
    {
        $query = BookingDayAllocation::query()
            ->where('allocation_status', BookingDayAllocation::STATUS_RELEASED)
            ->where('release_reason', BookingAllocationLifecycleService::REASON_ORGANIZER_DAY_EXCEPTION)
            ->whereNull('active_lock')
            ->whereExists(function ($exists) {
                $exists->select(DB::raw(1))
                    ->from('booking_day_allocations as retained')
                    ->whereColumn('retained.booking_id', 'booking_day_allocations.booking_id')
                    ->whereColumn('retained.event_site_id', 'booking_day_allocations.event_site_id')
                    ->where('retained.active_lock', 1)
                    ->whereColumn('retained.event_day_id', '!=', 'booking_day_allocations.event_day_id');
            });

        if (! empty($filters['event_id'])) {
            $query->whereHas('eventDay', fn (Builder $dayQuery) =>
                $dayQuery->where('carboot_event_id', (int) $filters['event_id']));
        }

        if (! empty($filters['event_day_id'])) {
            $query->where('event_day_id', (int) $filters['event_day_id']);
        }

        if (! empty($filters['release_reason'])) {
            $query->where('release_reason', $filters['release_reason']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereHas('eventDay', fn (Builder $dayQuery) =>
                $dayQuery->whereDate('operational_date', '>=', $filters['date_from']));
        }

        if (! empty($filters['date_to'])) {
            $query->whereHas('eventDay', fn (Builder $dayQuery) =>
                $dayQuery->whereDate('operational_date', '<=', $filters['date_to']));
        }

        if (! empty($filters['payment_state'])) {
            $paymentState = $filters['payment_state'];
            $query->whereHas('booking', function (Builder $bookingQuery) use ($paymentState) {
                if ($paymentState === VendorBookingPresenter::PAYMENT_STATE_UNPAID) {
                    $bookingQuery->where(function (Builder $builder) {
                        $builder->whereDoesntHave('invoice')
                            ->orWhereHas('invoice', fn (Builder $invoiceQuery) =>
                                $invoiceQuery->where('payment_status', 'Unpaid'));
                    });

                    return;
                }

                $invoiceStatus = match ($paymentState) {
                    VendorBookingPresenter::PAYMENT_STATE_PAID => 'Paid',
                    VendorBookingPresenter::PAYMENT_STATE_PAYMENT_SUBMITTED => 'Pending Verification',
                    default => 'Unpaid',
                };

                $bookingQuery->whereHas('invoice', fn (Builder $invoiceQuery) =>
                    $invoiceQuery->where('payment_status', $invoiceStatus));
            });
        }

        if (! empty($filters['search'])) {
            $needle = '%' . mb_strtolower($filters['search']) . '%';
            $search = $filters['search'];

            $query->where(function (Builder $builder) use ($needle, $search) {
                $builder->where('booking_id', 'like', '%' . $search . '%')
                    ->orWhereHas('booking.carbootEvent', fn (Builder $eventQuery) =>
                        $eventQuery->whereRaw('LOWER(title) LIKE ?', [$needle]))
                    ->orWhereHas('eventSite', fn (Builder $siteQuery) =>
                        $siteQuery->whereRaw('LOWER(label) LIKE ?', [$needle]))
                    ->orWhereHas('booking.user', fn (Builder $userQuery) =>
                        $userQuery->whereRaw('LOWER(name) LIKE ?', [$needle]))
                    ->orWhereHas('booking.user.businessProfile', fn (Builder $profileQuery) =>
                        $profileQuery->whereRaw('LOWER(business_name) LIKE ?', [$needle]));
            });
        }

        return $query;
    }

    /**
     * @param  Collection<int, int>  $eventIds
     * @return array<int, Collection<int, EventDay>>
     */
    private function activeDaysByEvent(Collection $eventIds): array
    {
        if ($eventIds->isEmpty()) {
            return [];
        }

        return EventDay::query()
            ->whereIn('carboot_event_id', $eventIds->all())
            ->active()
            ->ordered()
            ->get()
            ->groupBy('carboot_event_id')
            ->map(fn (Collection $days) => $days->values())
            ->all();
    }

    /**
     * @param  array<int, Collection<int, EventDay>>  $activeDaysByEvent
     * @return array<int, list<int>>
     */
    private function occupiedSiteIdsByEvent(array $activeDaysByEvent): array
    {
        $occupied = [];

        foreach ($activeDaysByEvent as $eventId => $days) {
            $dayIds = $days->pluck('id')->all();
            if ($dayIds === []) {
                $occupied[$eventId] = [];

                continue;
            }

            $occupied[$eventId] = BookingDayAllocation::query()
                ->whereIn('event_day_id', $dayIds)
                ->activeOccupancy()
                ->distinct()
                ->pluck('event_site_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $occupied;
    }

    /**
     * @param  Collection<int, BookingDayAllocation>  $releasedRows
     * @return array<string, bool>
     */
    private function activeConflictsForRows(Collection $releasedRows): array
    {
        $pairs = $releasedRows
            ->map(fn (BookingDayAllocation $row) => [
                'event_day_id' => (int) $row->event_day_id,
                'event_site_id' => (int) $row->event_site_id,
            ])
            ->unique(fn (array $pair) => $pair['event_day_id'] . ':' . $pair['event_site_id'])
            ->values();

        if ($pairs->isEmpty()) {
            return [];
        }

        $dayIds = $pairs->pluck('event_day_id')->unique()->all();
        $siteIds = $pairs->pluck('event_site_id')->unique()->all();

        $active = BookingDayAllocation::query()
            ->whereIn('event_day_id', $dayIds)
            ->whereIn('event_site_id', $siteIds)
            ->activeOccupancy()
            ->get(['event_day_id', 'event_site_id']);

        $conflicts = [];
        foreach ($active as $row) {
            $conflicts[$row->event_day_id . ':' . $row->event_site_id] = true;
        }

        return $conflicts;
    }

    /**
     * @param  Collection<string, Collection<int, BookingDayAllocation>>  $grouped
     * @return array<string, string>
     */
    private function exceptionReasonsForGroups(Collection $grouped): array
    {
        $bookingIds = $grouped
            ->flatMap(fn (Collection $rows) => $rows->pluck('booking_id'))
            ->unique()
            ->values();

        if ($bookingIds->isEmpty()) {
            return [];
        }

        $exceptions = BookingAttendanceException::query()
            ->whereIn('booking_id', $bookingIds->all())
            ->with(['days' => fn ($query) => $query->where(
                'disposition',
                BookingAttendanceExceptionDay::DISPOSITION_RELEASED,
            )])
            ->orderBy('id')
            ->get();

        $reasons = [];
        foreach ($exceptions as $exception) {
            foreach ($exception->days as $day) {
                $key = $exception->booking_id . ':' . $day->event_day_id;
                $reasons[$key] = $exception->reason;
            }
        }

        return $reasons;
    }

    /**
     * @param  array<string, bool>  $conflicts
     * @return array<string, mixed>
     */
    private function deriveSiteRecovery(
        BookingDayAllocation $row,
        ?EventDay $eventDay,
        ?CarbootEvent $event,
        array $conflicts,
    ): array {
        $site = $row->eventSite;
        $pairKey = $row->event_day_id . ':' . $row->event_site_id;

        if ($eventDay?->starts_at !== null && $eventDay->starts_at->lte(now())) {
            return $this->sitePayload($site, self::RECOVERY_EXPIRED, 'This EventDay has already started or ended.');
        }

        if ($eventDay?->operational_status !== EventDay::STATUS_ACTIVE) {
            return $this->sitePayload(
                $site,
                self::RECOVERY_OPERATIONALLY_UNAVAILABLE,
                'This EventDay is no longer operationally active.',
            );
        }

        if ($event !== null && ! $this->isEventOperationallyBookable($event)) {
            return $this->sitePayload(
                $site,
                self::RECOVERY_OPERATIONALLY_UNAVAILABLE,
                'This event is no longer operationally bookable.',
            );
        }

        if ($site?->operational_status !== EventSite::STATUS_ACTIVE) {
            return $this->sitePayload(
                $site,
                self::RECOVERY_OPERATIONALLY_UNAVAILABLE,
                'This site is operationally unavailable.',
            );
        }

        if (isset($conflicts[$pairKey])) {
            return $this->sitePayload(
                $site,
                self::RECOVERY_FULLY_BLOCKED,
                'Occupied by another active booking',
            );
        }

        return $this->sitePayload($site, self::RECOVERY_RECOVERABLE, null);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $siteStates
     */
    private function deriveGroupRecoveryState(
        ?EventDay $eventDay,
        ?CarbootEvent $event,
        Collection $siteStates,
    ): string {
        if ($eventDay?->starts_at !== null && $eventDay->starts_at->lte(now())) {
            return self::RECOVERY_EXPIRED;
        }

        if ($eventDay?->operational_status !== EventDay::STATUS_ACTIVE) {
            return self::RECOVERY_OPERATIONALLY_UNAVAILABLE;
        }

        if ($event !== null && ! $this->isEventOperationallyBookable($event)) {
            return self::RECOVERY_OPERATIONALLY_UNAVAILABLE;
        }

        $recoverable = $siteStates->where('recovery_state', self::RECOVERY_RECOVERABLE)->count();
        $blocked = $siteStates->where('recovery_state', self::RECOVERY_FULLY_BLOCKED)->count();
        $unavailable = $siteStates->where('recovery_state', self::RECOVERY_OPERATIONALLY_UNAVAILABLE)->count();
        $total = $siteStates->count();

        if ($recoverable === $total) {
            return self::RECOVERY_RECOVERABLE;
        }

        if ($blocked === $total) {
            return self::RECOVERY_FULLY_BLOCKED;
        }

        if ($unavailable === $total) {
            return self::RECOVERY_OPERATIONALLY_UNAVAILABLE;
        }

        if ($recoverable > 0 && ($blocked > 0 || $unavailable > 0)) {
            return self::RECOVERY_PARTIALLY_BLOCKED;
        }

        if ($blocked > 0 && $recoverable === 0) {
            return self::RECOVERY_FULLY_BLOCKED;
        }

        return self::RECOVERY_PARTIALLY_BLOCKED;
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(?EventSite $site, string $state, ?string $blocker): array
    {
        return [
            'id' => $site?->id,
            'label' => $site?->label,
            'space_name' => $site?->space?->space_size,
            'recovery_state' => $state,
            'blocker' => $blocker,
        ];
    }

    private function isEventOperationallyBookable(CarbootEvent $event): bool
    {
        if ($event->status === 'Closed') {
            return false;
        }

        if ($event->ends_at !== null && $event->ends_at->lt(now())) {
            return false;
        }

        return true;
    }
}
