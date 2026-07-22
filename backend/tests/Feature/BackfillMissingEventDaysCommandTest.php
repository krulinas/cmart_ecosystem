<?php

namespace Tests\Feature;

use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * E.D.1 — event-days:backfill-missing command safety and behaviour.
 */
class BackfillMissingEventDaysCommandTest extends TestCase
{
    private array $createdUserIds = [];

    private array $createdEventIds = [];

    protected function tearDown(): void
    {
        if ($this->createdEventIds !== []) {
            EventDay::whereIn('carboot_event_id', $this->createdEventIds)->delete();
            CarbootEvent::whereIn('id', $this->createdEventIds)->delete();
            $this->createdEventIds = [];
        }

        if ($this->createdUserIds !== []) {
            User::whereIn('id', $this->createdUserIds)->delete();
            $this->createdUserIds = [];
        }

        parent::tearDown();
    }

    private function createZeroDayEvent(array $overrides = []): CarbootEvent
    {
        $starts = now()->addDays(70)->setTime(10, 0, 0);
        $event = CarbootEvent::query()->create(array_merge([
            'title' => 'ED1 Backfill '.uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(18, 0, 0),
            'status' => 'Available',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ], $overrides));
        $this->createdEventIds[] = $event->id;

        $this->assertSame(0, EventDay::query()->forEvent($event->id)->count());

        return $event;
    }

    public function test_default_execution_is_dry_run_and_performs_no_writes(): void
    {
        $event = $this->createZeroDayEvent();

        $exit = Artisan::call('event-days:backfill-missing');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('DRY-RUN', $output);
        $this->assertStringContainsString('event_id='.$event->id, $output);
        $this->assertSame(0, EventDay::query()->forEvent($event->id)->count());
    }

    public function test_apply_generates_days_for_eligible_zero_day_event(): void
    {
        $event = $this->createZeroDayEvent([
            'starts_at' => now()->addDays(71)->setTime(10, 0, 0),
            'ends_at' => now()->addDays(72)->setTime(18, 0, 0),
        ]);

        $exit = Artisan::call('event-days:backfill-missing', ['--apply' => true]);

        $this->assertSame(0, $exit);
        $this->assertSame(2, EventDay::query()->forEvent($event->id)->count());
    }

    public function test_event_with_existing_days_is_skipped(): void
    {
        $event = $this->createZeroDayEvent();
        EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $event->starts_at->toDateString(),
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);

        $before = EventDay::query()->forEvent($event->id)->count();
        $exit = Artisan::call('event-days:backfill-missing', ['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString($event->id.':existing_event_days', $output);
        $this->assertSame($before, EventDay::query()->forEvent($event->id)->count());
    }

    public function test_invalid_date_range_is_skipped_safely(): void
    {
        $event = $this->createZeroDayEvent();
        // Bypass model validation by forcing an invalid range at DB level for the skip path.
        CarbootEvent::query()->whereKey($event->id)->update([
            'starts_at' => now()->addDays(80)->setTime(18, 0, 0),
            'ends_at' => now()->addDays(80)->setTime(10, 0, 0),
        ]);

        $exit = Artisan::call('event-days:backfill-missing', ['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString($event->id.':invalid_date_range', $output);
        $this->assertSame(0, EventDay::query()->forEvent($event->id)->count());
    }

    public function test_re_running_apply_is_idempotent(): void
    {
        $event = $this->createZeroDayEvent();

        $this->assertSame(0, Artisan::call('event-days:backfill-missing', ['--apply' => true]));
        $firstIds = EventDay::query()->forEvent($event->id)->orderBy('id')->pluck('id')->all();
        $this->assertNotEmpty($firstIds);

        $this->assertSame(0, Artisan::call('event-days:backfill-missing', ['--apply' => true]));
        $secondIds = EventDay::query()->forEvent($event->id)->orderBy('id')->pluck('id')->all();

        $this->assertSame($firstIds, $secondIds);
    }
}
