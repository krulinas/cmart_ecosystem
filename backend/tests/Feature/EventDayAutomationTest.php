<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use App\Services\EventDayGenerator;
use App\Services\EventLayoutReadinessService;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * E.D.1 — automatic event_days materialization on event create/update.
 */
class EventDayAutomationTest extends TestCase
{
    private array $createdUserIds = [];

    private array $createdEventIds = [];

    private array $createdBookingIds = [];

    private array $createdAllocationIds = [];

    private array $createdSiteIds = [];

    protected function tearDown(): void
    {
        if ($this->createdAllocationIds !== []) {
            BookingDayAllocation::whereIn('id', $this->createdAllocationIds)->delete();
            $this->createdAllocationIds = [];
        }

        if ($this->createdBookingIds !== []) {
            Booking::whereIn('id', $this->createdBookingIds)->delete();
            $this->createdBookingIds = [];
        }

        if ($this->createdSiteIds !== []) {
            EventSite::whereIn('id', $this->createdSiteIds)->delete();
            $this->createdSiteIds = [];
        }

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

    private function createUser(string $role): User
    {
        $user = User::create([
            'name' => 'ED1 '.$role.' '.uniqid(),
            'email' => 'ed1-'.$role.'-'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]);
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function trackEventId(?int $eventId): void
    {
        if ($eventId) {
            $this->createdEventIds[] = $eventId;
        }
    }

    public function test_single_day_event_create_materializes_exactly_one_active_day(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $starts = now()->addDays(10)->setTime(10, 0, 0);
        $ends = $starts->copy()->setTime(22, 0, 0);

        $response = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Single '.$starts->toDateString(),
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends->format('Y-m-d H:i:s'),
            'status' => 'Available',
            'site_price' => '20.00',
        ]);

        $response->assertCreated();
        $eventId = (int) $response->json('event.id');
        $this->trackEventId($eventId);

        $days = EventDay::query()->forEvent($eventId)->ordered()->get();
        $this->assertCount(1, $days);
        $this->assertSame($starts->toDateString(), $days[0]->operational_date->toDateString());
        $this->assertSame(EventDay::STATUS_ACTIVE, $days[0]->operational_status);
        $this->assertSame($starts->format('Y-m-d H:i:s'), $days[0]->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame($ends->format('Y-m-d H:i:s'), $days[0]->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_multi_day_event_create_materializes_inclusive_calendar_days(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $starts = now()->addDays(20)->setTime(10, 0, 0);
        $ends = $starts->copy()->addDays(2)->setTime(18, 0, 0);

        $response = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Multi '.$starts->toDateString(),
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends->format('Y-m-d H:i:s'),
            'status' => 'Available',
            'site_price' => '20.00',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]);

        $response->assertCreated();
        $eventId = (int) $response->json('event.id');
        $this->trackEventId($eventId);

        $dates = EventDay::query()->forEvent($eventId)->ordered()->pluck('operational_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $this->assertSame([
            $starts->toDateString(),
            $starts->copy()->addDay()->toDateString(),
            $ends->toDateString(),
        ], $dates);
    }

    public function test_create_is_idempotent_and_does_not_duplicate_via_manual_generate_without_replace(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $starts = now()->addDays(12)->setTime(9, 0, 0);
        $ends = $starts->copy()->setTime(17, 0, 0);

        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Idempotent '.$starts->toDateString(),
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends->format('Y-m-d H:i:s'),
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);
        $this->assertSame(1, EventDay::query()->forEvent($eventId)->count());

        $this->postJson("/api/organizer/events/{$eventId}/days/generate")
            ->assertStatus(422);

        $this->assertSame(1, EventDay::query()->forEvent($eventId)->count());
    }

    public function test_community_cannot_create_carboot_events(): void
    {
        Sanctum::actingAs($this->createUser('community'));

        $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Forbidden',
            'starts_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(3)->addHours(4)->format('Y-m-d H:i:s'),
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertForbidden();
    }

    public function test_same_day_time_adjustment_keeps_one_day(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $day = now()->addDays(15)->toDateString();
        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Adjust '.$day,
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 18:00:00',
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);

        $this->putJson("/api/carboot-events/{$eventId}", [
            'starts_at' => $day.' 09:30:00',
            'ends_at' => $day.' 21:00:00',
        ])->assertOk();

        $days = EventDay::query()->forEvent($eventId)->ordered()->get();
        $this->assertCount(1, $days);
        $this->assertSame($day.' 09:30:00', $days[0]->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame($day.' 21:00:00', $days[0]->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_single_to_multi_day_extends_days_before_allocations(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $start = now()->addDays(25)->toDateString();
        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Extend '.$start,
            'starts_at' => $start.' 10:00:00',
            'ends_at' => $start.' 18:00:00',
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);

        $end = now()->addDays(27)->toDateString();
        $this->putJson("/api/carboot-events/{$eventId}", [
            'starts_at' => $start.' 10:00:00',
            'ends_at' => $end.' 18:00:00',
        ])->assertOk();

        $this->assertSame(3, EventDay::query()->forEvent($eventId)->count());
    }

    public function test_multi_day_shortened_before_allocations_removes_obsolete_days(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $start = now()->addDays(30)->toDateString();
        $end = now()->addDays(32)->toDateString();
        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Shorten '.$start,
            'starts_at' => $start.' 10:00:00',
            'ends_at' => $end.' 18:00:00',
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);
        $this->assertSame(3, EventDay::query()->forEvent($eventId)->count());

        $this->putJson("/api/carboot-events/{$eventId}", [
            'starts_at' => $start.' 10:00:00',
            'ends_at' => $start.' 18:00:00',
        ])->assertOk();

        $this->assertSame(1, EventDay::query()->forEvent($eventId)->count());
        $this->assertSame(
            $start,
            \Carbon\Carbon::parse(EventDay::query()->forEvent($eventId)->value('operational_date'))->format('Y-m-d')
        );
    }

    public function test_resaving_unchanged_dates_is_idempotent(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $day = now()->addDays(18)->toDateString();
        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Resave '.$day,
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 18:00:00',
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);
        $dayId = (int) EventDay::query()->forEvent($eventId)->value('id');

        $this->putJson("/api/carboot-events/{$eventId}", [
            'title' => 'ED1 Resave Updated Title',
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 18:00:00',
        ])->assertOk();

        $this->assertSame(1, EventDay::query()->forEvent($eventId)->count());
        $this->assertSame($dayId, (int) EventDay::query()->forEvent($eventId)->value('id'));
    }

    public function test_unrelated_metadata_update_does_not_replace_days(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $day = now()->addDays(19)->toDateString();
        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Meta '.$day,
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 18:00:00',
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);
        $dayId = (int) EventDay::query()->forEvent($eventId)->value('id');

        $this->putJson("/api/carboot-events/{$eventId}", [
            'title' => 'ED1 Meta Renamed',
            'description' => 'Updated description only',
        ])->assertOk();

        $this->assertSame($dayId, (int) EventDay::query()->forEvent($eventId)->value('id'));
        $this->assertSame(1, EventDay::query()->forEvent($eventId)->count());
    }

    public function test_date_change_blocked_when_allocation_history_exists_without_partial_update(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $day = now()->addDays(40)->toDateString();
        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Locked '.$day,
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 18:00:00',
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);
        $event = CarbootEvent::query()->findOrFail($eventId);
        $originalTitle = $event->title;

        $this->attachAllocationHistory($event);

        $response = $this->putJson("/api/carboot-events/{$eventId}", [
            'title' => 'Should Not Apply',
            'starts_at' => $day.' 11:00:00',
            'ends_at' => $day.' 19:00:00',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error', EventDayGenerator::ERROR_OPERATING_DATES_LOCKED);

        $event->refresh();
        $this->assertSame($originalTitle, $event->title);
        $this->assertSame($day.' 10:00:00', $event->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, EventDay::query()->forEvent($eventId)->count());
    }

    public function test_metadata_remains_editable_when_allocations_exist(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $day = now()->addDays(41)->toDateString();
        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Meta Locked '.$day,
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 18:00:00',
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);
        $this->attachAllocationHistory(CarbootEvent::query()->findOrFail($eventId));

        $this->putJson("/api/carboot-events/{$eventId}", [
            'title' => 'ED1 Meta Locked Renamed',
            'description' => 'Safe metadata edit',
        ])->assertOk()
            ->assertJsonPath('event.title', 'ED1 Meta Locked Renamed');
    }

    public function test_manual_day_outside_event_range_rejected_on_create_and_update(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $day = now()->addDays(50)->toDateString();
        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Range '.$day,
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 18:00:00',
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);

        $outside = now()->addDays(60)->toDateString();
        $this->postJson("/api/organizer/events/{$eventId}/days", [
            'operational_date' => $outside,
            'starts_at' => $outside.' 10:00:00',
            'ends_at' => $outside.' 12:00:00',
        ])->assertStatus(422)
            ->assertJsonPath('error', EventDayGenerator::ERROR_DAY_OUTSIDE_EVENT_RANGE);

        $dayId = (int) EventDay::query()->forEvent($eventId)->value('id');
        $this->patchJson("/api/organizer/event-days/{$dayId}", [
            'operational_date' => $outside,
            'starts_at' => $outside.' 10:00:00',
            'ends_at' => $outside.' 12:00:00',
        ])->assertStatus(422)
            ->assertJsonPath('error', EventDayGenerator::ERROR_DAY_OUTSIDE_EVENT_RANGE);
    }

    public function test_auto_generated_days_clear_no_active_event_days_readiness_blocker(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $day = now()->addDays(55)->toDateString();
        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Ready Days '.$day,
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 18:00:00',
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);

        $assessment = app(EventLayoutReadinessService::class)
            ->assess(CarbootEvent::query()->findOrFail($eventId));

        $codes = array_column($assessment['blocking_reasons'], 'code');
        $this->assertNotContains('NO_ACTIVE_EVENT_DAYS', $codes);
        $this->assertContains('NO_ACTIVE_LAYOUT_ROWS', $codes);
        $this->assertFalse($assessment['operational_ready']);
        $this->assertFalse($assessment['public_ready']);
    }

    public function test_single_session_overnight_still_creates_one_day_via_api(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $starts = now()->addDays(8)->setTime(22, 0, 0);
        $ends = $starts->copy()->addHours(3);

        $create = $this->postJson('/api/carboot-events', [
            'title' => 'ED1 Overnight '.$starts->toDateString(),
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends->format('Y-m-d H:i:s'),
            'status' => 'Available',
            'site_price' => '20.00',
            'day_generation_mode' => CarbootEvent::DAY_MODE_SINGLE_SESSION,
        ])->assertCreated();

        $eventId = (int) $create->json('event.id');
        $this->trackEventId($eventId);

        $this->assertSame(1, EventDay::query()->forEvent($eventId)->count());
        $this->assertSame(
            $starts->toDateString(),
            \Carbon\Carbon::parse(EventDay::query()->forEvent($eventId)->value('operational_date'))->format('Y-m-d')
        );
    }

    private function attachAllocationHistory(CarbootEvent $event): void
    {
        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 20.00, 'status' => 'Available'],
        );

        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => 'ED1-'.uniqid(),
            'row_label' => 'Z',
            'position_number' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->createdSiteIds[] = $site->id;

        $vendor = $this->createUser('community');
        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'ED1 allocation fixture with sufficient product detail length.',
            'approval_status' => 'Approved',
        ]);
        $this->createdBookingIds[] = $booking->id;

        $day = EventDay::query()->forEvent($event->id)->firstOrFail();
        $allocation = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
            'allocation_status' => BookingDayAllocation::STATUS_RESERVED,
            'reserved_at' => now(),
            'active_lock' => BookingDayAllocation::activeLockForStatus(
                BookingDayAllocation::STATUS_RESERVED
            ),
        ]);
        $this->createdAllocationIds[] = $allocation->id;
    }
}
