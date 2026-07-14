<?php

namespace Tests\Feature;

use App\Models\CarbootEvent;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2A.4 — physical event_sites foundation.
 */
class EventSiteFoundationTest extends TestCase
{
    private array $createdUserIds = [];
    private array $createdEventIds = [];
    private array $createdSiteIds = [];

    protected function tearDown(): void
    {
        if ($this->createdSiteIds !== []) {
            EventSite::whereIn('id', $this->createdSiteIds)->delete();
            $this->createdSiteIds = [];
        }

        if ($this->createdEventIds !== []) {
            CarbootEvent::whereIn('id', $this->createdEventIds)->delete();
            $this->createdEventIds = [];
        }

        if ($this->createdUserIds !== []) {
            User::whereIn('id', $this->createdUserIds)->delete();
            $this->createdUserIds = [];
        }

        parent::tearDown();
    }

    private function createUser(string $role, array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'EventSite Test ' . ucfirst($role) . ' ' . uniqid(),
            'email' => 'eventsite-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => 'none',
        ], $overrides));

        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createEvent(): CarbootEvent
    {
        $event = CarbootEvent::query()->create([
            'title' => 'Event Site Foundation ' . uniqid(),
            'starts_at' => now()->addDays(7)->setTime(8, 0),
            'ends_at' => now()->addDays(7)->setTime(17, 0),
            'status' => 'Available',
            'description' => 'Phase 2A.4 event site test',
            'max_slots' => 50,
        ]);
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

    private function sitePayload(Space $space, array $overrides = []): array
    {
        return array_merge([
            'space_id' => $space->id,
            'label' => 'A01',
            'row_label' => 'A',
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ], $overrides);
    }

    public function test_organizer_can_create_and_list_event_sites(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($organizer);

        $create = $this->postJson(
            "/api/organizer/events/{$event->id}/sites",
            $this->sitePayload($space, ['label' => 'A05', 'position_number' => 5, 'grid_column' => 5]),
        );

        $create->assertCreated()
            ->assertJsonPath('site.label', 'A05')
            ->assertJsonPath('site.row_label', 'A')
            ->assertJsonPath('site.operational_status', 'active')
            ->assertJsonPath('site.space_id', $space->id);

        $siteId = (int) $create->json('site.id');
        $this->createdSiteIds[] = $siteId;

        $list = $this->getJson("/api/organizer/events/{$event->id}/sites");
        $list->assertOk()
            ->assertJsonPath('event_id', $event->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('sites.0.label', 'A05');
    }

    public function test_duplicate_label_on_same_event_is_rejected(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($organizer);

        $first = $this->postJson(
            "/api/organizer/events/{$event->id}/sites",
            $this->sitePayload($space, ['label' => 'B01', 'row_label' => 'B', 'position_number' => 1]),
        );
        $first->assertCreated();
        $this->createdSiteIds[] = (int) $first->json('site.id');

        $second = $this->postJson(
            "/api/organizer/events/{$event->id}/sites",
            $this->sitePayload($space, [
                'label' => 'B01',
                'row_label' => 'B',
                'position_number' => 2,
                'grid_column' => 2,
            ]),
        );

        $second->assertStatus(422)
            ->assertJsonPath('error', 'duplicate_label');
    }

    public function test_same_label_allowed_on_different_events(): void
    {
        $organizer = $this->createUser('organizer');
        $eventA = $this->createEvent();
        $eventB = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($organizer);

        $a = $this->postJson(
            "/api/organizer/events/{$eventA->id}/sites",
            $this->sitePayload($space, ['label' => 'VIP-01', 'row_label' => 'VIP', 'position_number' => 1]),
        );
        $a->assertCreated();
        $this->createdSiteIds[] = (int) $a->json('site.id');

        $b = $this->postJson(
            "/api/organizer/events/{$eventB->id}/sites",
            $this->sitePayload($space, ['label' => 'VIP-01', 'row_label' => 'VIP', 'position_number' => 1]),
        );
        $b->assertCreated();
        $this->createdSiteIds[] = (int) $b->json('site.id');
    }

    public function test_duplicate_row_position_on_same_event_is_rejected(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($organizer);

        $first = $this->postJson(
            "/api/organizer/events/{$event->id}/sites",
            $this->sitePayload($space, ['label' => 'A01', 'row_label' => 'A', 'position_number' => 1]),
        );
        $first->assertCreated();
        $this->createdSiteIds[] = (int) $first->json('site.id');

        $second = $this->postJson(
            "/api/organizer/events/{$event->id}/sites",
            $this->sitePayload($space, [
                'label' => 'A99',
                'row_label' => 'A',
                'position_number' => 1,
                'grid_column' => 9,
            ]),
        );

        $second->assertStatus(422)
            ->assertJsonPath('error', 'duplicate_row_position');
    }

    public function test_organizer_can_update_operational_status_and_delete(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($organizer);

        $create = $this->postJson(
            "/api/organizer/events/{$event->id}/sites",
            $this->sitePayload($space, ['label' => 'C03', 'row_label' => 'C', 'position_number' => 3, 'grid_row' => 3, 'grid_column' => 3]),
        );
        $create->assertCreated();
        $siteId = (int) $create->json('site.id');
        $this->createdSiteIds[] = $siteId;

        $update = $this->patchJson("/api/organizer/event-sites/{$siteId}", [
            'operational_status' => EventSite::STATUS_DISABLED,
        ]);
        $update->assertOk()
            ->assertJsonPath('site.operational_status', 'disabled');

        $delete = $this->deleteJson("/api/organizer/event-sites/{$siteId}");
        $delete->assertOk();

        $this->assertDatabaseMissing('event_sites', ['id' => $siteId]);
        $this->createdSiteIds = array_values(array_filter(
            $this->createdSiteIds,
            fn (int $id) => $id !== $siteId,
        ));
    }

    public function test_cmart_management_cannot_manage_event_sites(): void
    {
        $venue = $this->createUser('cmart_management');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($venue);

        $this->getJson("/api/organizer/events/{$event->id}/sites")->assertForbidden();
        $this->postJson(
            "/api/organizer/events/{$event->id}/sites",
            $this->sitePayload($space),
        )->assertForbidden();
    }

    public function test_community_vendor_cannot_manage_event_sites(): void
    {
        $vendor = $this->createUser('community', ['vendor_status' => 'approved']);
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($vendor);

        $this->getJson("/api/organizer/events/{$event->id}/sites")->assertForbidden();
        $this->postJson(
            "/api/organizer/events/{$event->id}/sites",
            $this->sitePayload($space),
        )->assertForbidden();
    }

    public function test_super_admin_can_create_event_site(): void
    {
        $admin = $this->createUser('super_admin');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        Sanctum::actingAs($admin);

        $create = $this->postJson(
            "/api/organizer/events/{$event->id}/sites",
            $this->sitePayload($space, ['label' => 'D10', 'row_label' => 'D', 'position_number' => 10, 'grid_column' => 10]),
        );

        $create->assertCreated();
        $this->createdSiteIds[] = (int) $create->json('site.id');
    }
}
