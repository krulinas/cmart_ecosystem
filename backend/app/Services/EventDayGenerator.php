<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 2A.5 / E.D.1 — generate, replace, or auto-materialize operational event days.
 */
class EventDayGenerator
{
    public const MODE_CALENDAR_DAYS = 'calendar_days';
    public const MODE_SINGLE_SESSION = 'single_session';

    public const MODES = [
        self::MODE_CALENDAR_DAYS,
        self::MODE_SINGLE_SESSION,
    ];

    public const ERROR_OPERATING_DATES_LOCKED = 'event_operating_dates_locked_by_allocations';

    public const ERROR_DAY_OUTSIDE_EVENT_RANGE = 'event_day_outside_event_range';

    /**
     * @return array{created: list<EventDay>, replaced: int, mode: string}
     */
    public function generate(CarbootEvent $event, bool $replaceExisting = false): array
    {
        $mode = $this->resolveMode($event);
        $existingCount = EventDay::query()->forEvent($event->id)->count();

        if ($existingCount > 0 && ! $replaceExisting) {
            throw new InvalidArgumentException(
                'Event days already exist for this event. Pass replace_existing=true to regenerate.'
            );
        }

        $plans = $this->planForEvent($event);

        return DB::transaction(function () use ($event, $plans, $existingCount, $mode, $replaceExisting) {
            $replaced = 0;

            if ($replaceExisting && $existingCount > 0) {
                $existingDays = EventDay::query()->forEvent($event->id)->get();
                if ($this->collectionHasAllocationHistory($existingDays)) {
                    throw new DomainConflictException(
                        'Cannot replace event days while allocation history exists. Existing schedule was preserved.',
                        'event_day_replace_blocked_by_history',
                    );
                }

                $replaced = EventDay::query()->forEvent($event->id)->delete();
            }

            $created = $this->createDaysFromPlans($event, $plans);

            return [
                'created' => $created,
                'replaced' => $replaced,
                'mode' => $mode,
            ];
        });
    }

    /**
     * Idempotent create/sync used by event save (E.D.1).
     *
     * @return array{
     *   action: string,
     *   created: list<EventDay>,
     *   replaced: int,
     *   mode: string,
     *   days: list<EventDay>
     * }
     */
    public function materializeForEvent(CarbootEvent $event): array
    {
        return DB::transaction(function () use ($event) {
            $event = CarbootEvent::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $mode = $this->resolveMode($event);
            $plans = $this->planForEvent($event);

            /** @var Collection<int, EventDay> $existing */
            $existing = EventDay::query()
                ->forEvent($event->id)
                ->orderBy('operational_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($this->plansMatchExisting($existing, $plans)) {
                return [
                    'action' => 'unchanged',
                    'created' => [],
                    'replaced' => 0,
                    'mode' => $mode,
                    'days' => $existing->all(),
                ];
            }

            if ($existing->isNotEmpty() && $this->collectionHasAllocationHistory($existing)) {
                throw new DomainConflictException(
                    'This event already has vendor booking allocations. Its operating dates cannot be changed because existing bookings depend on those dates.',
                    self::ERROR_OPERATING_DATES_LOCKED,
                );
            }

            $replaced = 0;
            if ($existing->isNotEmpty()) {
                $replaced = EventDay::query()->forEvent($event->id)->delete();
            }

            $created = $this->createDaysFromPlans($event, $plans);

            return [
                'action' => $existing->isEmpty() ? 'created' : 'replaced',
                'created' => $created,
                'replaced' => $replaced,
                'mode' => $mode,
                'days' => $created,
            ];
        });
    }

    public function eventHasAllocationHistory(CarbootEvent $event): bool
    {
        $dayIds = EventDay::query()
            ->forEvent($event->id)
            ->pluck('id');

        if ($dayIds->isEmpty()) {
            return false;
        }

        return BookingDayAllocation::query()
            ->whereIn('event_day_id', $dayIds)
            ->exists();
    }

    /**
     * Ensure a manual day window fits the parent event operating range (MYT).
     *
     * @throws InvalidArgumentException
     */
    public function assertDayFitsEvent(
        CarbootEvent $event,
        string $operationalDate,
        string $startsAt,
        string $endsAt,
    ): void {
        $tz = config('app.timezone', 'Asia/Kuala_Lumpur');

        if (! $event->starts_at || ! $event->ends_at) {
            throw new InvalidArgumentException(
                'The parent event must have starts_at and ends_at before event days can be configured.'
            );
        }

        $eventStart = $event->starts_at->copy()->timezone($tz);
        $eventEnd = $event->ends_at->copy()->timezone($tz);

        if ($eventEnd->lte($eventStart)) {
            throw new InvalidArgumentException('Event ends_at must be after starts_at.');
        }

        $opDate = Carbon::parse($operationalDate, $tz)->toDateString();
        $rangeStart = $eventStart->toDateString();
        $rangeEnd = $eventEnd->toDateString();

        if ($opDate < $rangeStart || $opDate > $rangeEnd) {
            throw new InvalidArgumentException(
                'The operational date must fall within the event start and end dates.'
            );
        }

        $dayStart = Carbon::parse($startsAt, $tz)->timezone($tz);
        $dayEnd = Carbon::parse($endsAt, $tz)->timezone($tz);

        if ($dayEnd->lte($dayStart)) {
            throw new InvalidArgumentException('Event day ends_at must be after starts_at.');
        }

        if ($dayStart->lt($eventStart) || $dayEnd->gt($eventEnd)) {
            throw new InvalidArgumentException(
                'Event day start and end times must fall within the parent event operating window.'
            );
        }
    }

    /**
     * @return list<array{operational_date: string, starts_at: Carbon, ends_at: Carbon}>
     */
    public function planForEvent(CarbootEvent $event): array
    {
        $mode = $this->resolveMode($event);

        return $mode === self::MODE_SINGLE_SESSION
            ? $this->planSingleSession($event)
            : $this->planCalendarDays($event);
    }

    /**
     * @return list<array{operational_date: string, starts_at: Carbon, ends_at: Carbon}>
     */
    public function planCalendarDays(CarbootEvent $event): array
    {
        $tz = config('app.timezone', 'Asia/Kuala_Lumpur');
        $starts = $event->starts_at->copy()->timezone($tz);
        $ends = $event->ends_at->copy()->timezone($tz);

        if ($ends->lte($starts)) {
            throw new InvalidArgumentException('Event ends_at must be after starts_at.');
        }

        $startDate = $starts->toDateString();
        $endDate = $ends->toDateString();
        $period = CarbonPeriod::create($startDate, $endDate);

        $plans = [];
        foreach ($period as $date) {
            $day = Carbon::parse($date->toDateString(), $tz)->startOfDay();
            $dayStart = $day->equalTo($starts->copy()->startOfDay())
                ? $starts->copy()
                : $day->copy()->setTime(0, 0, 0);
            $dayEnd = $day->equalTo($ends->copy()->startOfDay())
                ? $ends->copy()
                : $day->copy()->setTime(23, 59, 59);

            $plans[] = [
                'operational_date' => $day->toDateString(),
                'starts_at' => $dayStart,
                'ends_at' => $dayEnd,
            ];
        }

        return $plans;
    }

    /**
     * @return list<array{operational_date: string, starts_at: Carbon, ends_at: Carbon}>
     */
    public function planSingleSession(CarbootEvent $event): array
    {
        $tz = config('app.timezone', 'Asia/Kuala_Lumpur');
        $starts = $event->starts_at->copy()->timezone($tz);
        $ends = $event->ends_at->copy()->timezone($tz);

        if ($ends->lte($starts)) {
            throw new InvalidArgumentException('Event ends_at must be after starts_at.');
        }

        return [[
            'operational_date' => $starts->toDateString(),
            'starts_at' => $starts,
            'ends_at' => $ends,
        ]];
    }

    private function resolveMode(CarbootEvent $event): string
    {
        $mode = $event->day_generation_mode ?: self::MODE_CALENDAR_DAYS;

        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Unsupported day_generation_mode [{$mode}].");
        }

        return $mode;
    }

    /**
     * @param  list<array{operational_date: string, starts_at: Carbon, ends_at: Carbon}>  $plans
     * @return list<EventDay>
     */
    private function createDaysFromPlans(CarbootEvent $event, array $plans): array
    {
        $created = [];
        foreach ($plans as $index => $plan) {
            $created[] = EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $plan['operational_date'],
                'starts_at' => $plan['starts_at'],
                'ends_at' => $plan['ends_at'],
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => $index + 1,
            ]);
        }

        return $created;
    }

    /**
     * @param  Collection<int, EventDay>  $existing
     * @param  list<array{operational_date: string, starts_at: Carbon, ends_at: Carbon}>  $plans
     */
    private function plansMatchExisting(Collection $existing, array $plans): bool
    {
        if ($existing->count() !== count($plans)) {
            return false;
        }

        $tz = config('app.timezone', 'Asia/Kuala_Lumpur');

        foreach ($existing->values() as $index => $day) {
            $plan = $plans[$index];
            $existingDate = optional($day->operational_date)?->toDateString();
            $existingStart = optional($day->starts_at)?->copy()->timezone($tz)->format('Y-m-d H:i:s');
            $existingEnd = optional($day->ends_at)?->copy()->timezone($tz)->format('Y-m-d H:i:s');
            $planStart = $plan['starts_at']->copy()->timezone($tz)->format('Y-m-d H:i:s');
            $planEnd = $plan['ends_at']->copy()->timezone($tz)->format('Y-m-d H:i:s');

            if (
                $existingDate !== $plan['operational_date']
                || $existingStart !== $planStart
                || $existingEnd !== $planEnd
                || $day->operational_status !== EventDay::STATUS_ACTIVE
                || (int) $day->display_order !== ($index + 1)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, EventDay>  $days
     */
    private function collectionHasAllocationHistory(Collection $days): bool
    {
        if ($days->isEmpty()) {
            return false;
        }

        return BookingDayAllocation::query()
            ->whereIn('event_day_id', $days->pluck('id'))
            ->exists();
    }
}
