<?php

namespace Tests\Feature;

use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2A.5 — layout generation + Organizer-defined event days.
 */
class EventLayoutAndDaysTest extends TestCase
{
    private array $createdUserIds = [];
    private array $createdEventIds = [];

    protected function tearDown(): void
    {
        if ($this->createdEventIds !== []) {
            EventSite::whereIn('carboot_event_id', $this->createdEventIds)->delete();
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
            'name' => 'LayoutDays ' . ucfirst($role) . ' ' . uniqid(),
            'email' => 'layout-days-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => 'none',
        ]);
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createEvent(array $overrides = []): CarbootEvent
    {
        $event = CarbootEvent::query()->create(array_merge([
            'title' => 'Layout Days Event ' . uniqid(),
            'starts_at' => now()->addDays(7)->setTime(8, 0),
            'ends_at' => now()->addDays(7)->setTime(17, 0),
            'status' => 'Available',
            'description' => 'Phase 2A.5 test',
            'max_slots' => 50,
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ], $overrides));
        $this->createdEventIds[] = $event->id;

        return $event;
    }

    private function standardSpace(): Space
    {
        return Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 20.00, 'status' => 'Available'],
        );
    }

    public function test_organizer_can_bulk_generate_parking_layout(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($organizer);

        $response = $this->postJson("/api/organizer/events/{$event->id}/sites/generate", [
            'space_id' => $space->id,
            'rows' => [
                ['row_label' => 'A', 'count' => 3, 'start_position' => 1, 'grid_row' => 1],
                ['row_label' => 'B', 'count' => 2, 'start_position' => 1, 'grid_row' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('created_count', 5)
            ->assertJsonPath('sites.0.label', 'A01')
            ->assertJsonPath('sites.3.label', 'B01');

        $this->assertSame(5, EventSite::query()->forEvent($event->id)->count());
        $this->assertDatabaseHas('event_sites', [
            'carboot_event_id' => $event->id,
            'label' => 'A03',
            'row_label' => 'A',
            'position_number' => 3,
        ]);
    }

    public function test_bulk_generate_without_replace_rejects_collisions(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($organizer);

        $this->postJson("/api/organizer/events/{$event->id}/sites/generate", [
            'space_id' => $space->id,
            'rows' => [
                ['row_label' => 'A', 'count' => 2],
            ],
        ])->assertCreated();

        $this->postJson("/api/organizer/events/{$event->id}/sites/generate", [
            'space_id' => $space->id,
            'rows' => [
                ['row_label' => 'A', 'count' => 2],
            ],
        ])->assertStatus(422);
    }

    public function test_bulk_generate_replace_existing_replaces_layout(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($organizer);

        $this->postJson("/api/organizer/events/{$event->id}/sites/generate", [
            'space_id' => $space->id,
            'rows' => [
                ['row_label' => 'A', 'count' => 2],
            ],
        ])->assertCreated();

        $response = $this->postJson("/api/organizer/events/{$event->id}/sites/generate", [
            'space_id' => $space->id,
            'replace_existing' => true,
            'rows' => [
                ['row_label' => 'VIP', 'label_prefix' => 'VIP-', 'count' => 1, 'start_position' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('replaced', 2)
            ->assertJsonPath('sites.0.label', 'VIP-01');

        $this->assertSame(1, EventSite::query()->forEvent($event->id)->count());
    }

    public function test_calendar_days_generation_creates_two_days_for_weekend_event(): void
    {
        $organizer = $this->createUser('organizer');
        $saturday = now()->next('Saturday')->setTime(8, 0, 0);
        $sunday = $saturday->copy()->addDay()->setTime(17, 0, 0);

        $event = $this->createEvent([
            'starts_at' => $saturday,
            'ends_at' => $sunday,
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]);

        Sanctum::actingAs($organizer);

        $response = $this->postJson("/api/organizer/events/{$event->id}/days/generate", [
            'replace_existing' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('day_generation_mode', 'calendar_days');

        $this->assertSame(2, EventDay::query()->forEvent($event->id)->count());
        $this->assertDatabaseHas('event_days', [
            'carboot_event_id' => $event->id,
            'operational_date' => $saturday->toDateString(),
        ]);
        $this->assertDatabaseHas('event_days', [
            'carboot_event_id' => $event->id,
            'operational_date' => $sunday->toDateString(),
        ]);
    }

    public function test_single_session_overnight_creates_one_event_day(): void
    {
        $organizer = $this->createUser('organizer');
        $starts = now()->addDays(3)->setTime(22, 0, 0);
        $ends = $starts->copy()->addHours(3);

        $event = $this->createEvent([
            'starts_at' => $starts,
            'ends_at' => $ends,
            'day_generation_mode' => CarbootEvent::DAY_MODE_SINGLE_SESSION,
        ]);

        Sanctum::actingAs($organizer);

        $response = $this->postJson("/api/organizer/events/{$event->id}/days/generate", [
            'day_generation_mode' => 'single_session',
        ]);

        $response->assertCreated()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('days.0.operational_date', $starts->toDateString());

        $this->assertSame(1, EventDay::query()->forEvent($event->id)->count());
    }

    public function test_generate_days_without_replace_rejects_when_days_exist(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $this->postJson("/api/organizer/events/{$event->id}/days/generate")->assertCreated();
        $this->postJson("/api/organizer/events/{$event->id}/days/generate")->assertStatus(422);
    }

    public function test_organizer_can_manually_create_and_disable_event_day(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $date = now()->addDays(10)->toDateString();

        Sanctum::actingAs($organizer);

        $create = $this->postJson("/api/organizer/events/{$event->id}/days", [
            'operational_date' => $date,
            'starts_at' => $date . ' 09:00:00',
            'ends_at' => $date . ' 17:00:00',
            'display_order' => 1,
        ]);

        $create->assertCreated()
            ->assertJsonPath('day.operational_date', $date)
            ->assertJsonPath('day.operational_status', 'active');

        $dayId = (int) $create->json('day.id');

        $this->patchJson("/api/organizer/event-days/{$dayId}", [
            'operational_status' => EventDay::STATUS_DISABLED,
        ])->assertOk()
            ->assertJsonPath('day.operational_status', 'disabled');

        $this->getJson("/api/organizer/events/{$event->id}/days")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_cmart_management_cannot_generate_layout_or_days(): void
    {
        $venue = $this->createUser('cmart_management');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($venue);

        $this->postJson("/api/organizer/events/{$event->id}/sites/generate", [
            'space_id' => $space->id,
            'rows' => [['row_label' => 'A', 'count' => 1]],
        ])->assertForbidden();

        $this->postJson("/api/organizer/events/{$event->id}/days/generate")
            ->assertForbidden();

        $this->getJson("/api/organizer/events/{$event->id}/days")
            ->assertForbidden();
    }

    public function test_event_payload_includes_day_generation_mode(): void
    {
        $organizer = $this->createUser('organizer');
        Sanctum::actingAs($organizer);

        $create = $this->postJson('/api/carboot-events', [
            'title' => 'Mode Payload Event ' . uniqid(),
            'starts_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(5)->addHours(6)->format('Y-m-d H:i:s'),
            'status' => 'Available',
            'day_generation_mode' => 'single_session',
            'max_slots' => 20,
        ]);

        $create->assertCreated()
            ->assertJsonPath('event.day_generation_mode', 'single_session');

        $eventId = (int) $create->json('event.id');
        $this->createdEventIds[] = $eventId;
    }
}
