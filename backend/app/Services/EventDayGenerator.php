<?php

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 2A.5 — generate or replace Organizer-defined operational event days.
 */
class EventDayGenerator
{
    public const MODE_CALENDAR_DAYS = 'calendar_days';
    public const MODE_SINGLE_SESSION = 'single_session';

    public const MODES = [
        self::MODE_CALENDAR_DAYS,
        self::MODE_SINGLE_SESSION,
    ];

    /**
     * @return array{created: list<EventDay>, replaced: int, mode: string}
     */
    public function generate(CarbootEvent $event, bool $replaceExisting = false): array
    {
        $mode = $event->day_generation_mode ?: self::MODE_CALENDAR_DAYS;

        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Unsupported day_generation_mode [{$mode}].");
        }

        $existingCount = EventDay::query()->forEvent($event->id)->count();

        if ($existingCount > 0 && ! $replaceExisting) {
            throw new InvalidArgumentException(
                'Event days already exist for this event. Pass replace_existing=true to regenerate.'
            );
        }

        $plans = $mode === self::MODE_SINGLE_SESSION
            ? $this->planSingleSession($event)
            : $this->planCalendarDays($event);

        return DB::transaction(function () use ($event, $plans, $existingCount, $mode, $replaceExisting) {
            $replaced = 0;

            if ($replaceExisting && $existingCount > 0) {
                $existingDays = EventDay::query()->forEvent($event->id)->get();
                $hasHistory = $existingDays->contains(
                    fn (EventDay $day) => $day->hasAllocationHistory()
                );

                if ($hasHistory) {
                    throw new DomainConflictException(
                        'Cannot replace event days while allocation history exists. Existing schedule was preserved.',
                        'event_day_replace_blocked_by_history',
                    );
                }

                $replaced = EventDay::query()->forEvent($event->id)->delete();
            }

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

            return [
                'created' => $created,
                'replaced' => $replaced,
                'mode' => $mode,
            ];
        });
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
}
