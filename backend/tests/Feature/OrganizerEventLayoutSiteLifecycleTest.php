<?php

namespace Tests\Feature;

use App\Models\EventSite;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\Phase35EventLayoutFixtures;
use Tests\TestCase;

class OrganizerEventLayoutSiteLifecycleTest extends TestCase
{
    use CleansUpTestFixtures;
    use Phase35EventLayoutFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function createUser(string $role): User
    {
        return $this->trackUser(User::create([
            'name' => 'Phase35 ' . $role . ' ' . uniqid(),
            'email' => 'p35-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => 'none',
        ]));
    }

    public function test_create_site_under_row_mirrors_row_label(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'Mirror Row']);

        $response = $this->postJson("/api/organizer/events/{$event->id}/layout/rows/{$row['id']}/sites", [
            'label' => 'M01',
            'space_id' => $this->standardSpace()->id,
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('site.row_label', 'Mirror Row')
            ->assertJsonPath('site.label', 'M01');

        $siteId = (int) $response->json('site.id');
        $this->createdSiteIds[] = $siteId;
    }

    public function test_generate_sites_with_padding_creates_a01_through_a05(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'Generate Row']);

        $response = $this->postJson(
            "/api/organizer/events/{$event->id}/layout/rows/{$row['id']}/sites/generate",
            [
                'space_id' => $this->standardSpace()->id,
                'count' => 5,
                'label_prefix' => 'A',
                'start_number' => 1,
                'number_padding' => 2,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('created_count', 5)
            ->assertJsonPath('sites.0.label', 'A01')
            ->assertJsonPath('sites.4.label', 'A05');

        foreach ($response->json('sites') as $site) {
            $this->createdSiteIds[] = (int) $site['id'];
        }
    }

    public function test_generate_conflict_is_atomic_and_leaves_site_count_unchanged(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'Conflict Row']);

        $this->createLayoutSiteViaApi($event, $row['id'], [
            'label' => 'A03',
            'position_number' => 3,
            'grid_column' => 3,
        ]);

        $beforeCount = EventSite::query()->forEvent($event->id)->count();

        $this->postJson(
            "/api/organizer/events/{$event->id}/layout/rows/{$row['id']}/sites/generate",
            [
                'space_id' => $this->standardSpace()->id,
                'count' => 5,
                'label_prefix' => 'A',
                'start_number' => 1,
                'number_padding' => 2,
            ],
        )
            ->assertStatus(409)
            ->assertJsonPath('error', 'SITE_LABEL_CONFLICT');

        $this->assertSame($beforeCount, EventSite::query()->forEvent($event->id)->count());
    }

    public function test_move_site_to_another_row_updates_row_label(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $sourceRow = $this->createLayoutRowViaApi($event, ['label' => 'Source Row']);
        $targetRow = $this->createLayoutRowViaApi($event, ['label' => 'Target Row']);
        $siteId = $this->createLayoutSiteViaApi($event, $sourceRow['id'], [
            'label' => 'T01',
            'position_number' => 1,
        ]);

        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$siteId}", [
            'event_layout_row_id' => $targetRow['id'],
        ])
            ->assertOk()
            ->assertJsonPath('site.row_label', 'Target Row')
            ->assertJsonPath('site.event_layout_row_id', $targetRow['id']);
    }

    public function test_site_structure_locked_after_allocation_history(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'Structure Row']);
        $siteId = $this->createLayoutSiteViaApi($event, $row['id'], [
            'label' => 'S01',
            'position_number' => 1,
        ]);
        $site = EventSite::query()->findOrFail($siteId);
        $this->seedReleasedAllocation($event, $site, $day);

        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$siteId}", [
            'label' => 'S99',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'SITE_STRUCTURE_LOCKED');
    }

    public function test_disable_blocked_with_active_allocation_allowed_with_released(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $activeRow = $this->createLayoutRowViaApi($event, ['label' => 'DisAct']);
        $activeSiteId = $this->createLayoutSiteViaApi($event, $activeRow['id'], [
            'label' => 'D01',
            'position_number' => 1,
        ]);
        $this->seedReservedAllocation(
            $event,
            EventSite::query()->findOrFail($activeSiteId),
            $day,
        );

        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$activeSiteId}", [
            'operational_status' => EventSite::STATUS_DISABLED,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'ACTIVE_ALLOCATIONS_PRESENT');

        $releasedRow = $this->createLayoutRowViaApi($event, ['label' => 'DisRel']);
        $releasedSiteId = $this->createLayoutSiteViaApi($event, $releasedRow['id'], [
            'label' => 'D02',
            'position_number' => 1,
        ]);
        $this->seedReleasedAllocation(
            $event,
            EventSite::query()->findOrFail($releasedSiteId),
            $day,
        );

        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$releasedSiteId}", [
            'operational_status' => EventSite::STATUS_DISABLED,
        ])
            ->assertOk()
            ->assertJsonPath('site.operational_status', EventSite::STATUS_DISABLED);
    }

    public function test_delete_blocked_with_history_allowed_without(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $historyRow = $this->createLayoutRowViaApi($event, ['label' => 'DelHist']);
        $historySiteId = $this->createLayoutSiteViaApi($event, $historyRow['id'], [
            'label' => 'X01',
            'position_number' => 1,
        ]);
        $this->seedReleasedAllocation(
            $event,
            EventSite::query()->findOrFail($historySiteId),
            $day,
        );

        $this->deleteJson("/api/organizer/events/{$event->id}/layout/sites/{$historySiteId}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'SITE_HAS_ALLOCATION_HISTORY');

        $cleanRow = $this->createLayoutRowViaApi($event, ['label' => 'DelClean']);
        $cleanSiteId = $this->createLayoutSiteViaApi($event, $cleanRow['id'], [
            'label' => 'X02',
            'position_number' => 1,
        ]);

        $this->deleteJson("/api/organizer/events/{$event->id}/layout/sites/{$cleanSiteId}")
            ->assertOk();

        $this->assertNull(EventSite::query()->find($cleanSiteId));
    }

    public function test_site_reorder_allowed_after_allocation_history(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'Reorder Row']);
        $siteA = $this->createLayoutSiteViaApi($event, $row['id'], [
            'label' => 'Q01',
            'position_number' => 1,
            'display_order' => 1,
        ]);
        $siteB = $this->createLayoutSiteViaApi($event, $row['id'], [
            'label' => 'Q02',
            'position_number' => 2,
            'grid_column' => 2,
            'display_order' => 2,
        ]);

        $this->seedReleasedAllocation(
            $event,
            EventSite::query()->findOrFail($siteA),
            $day,
        );

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$row['id']}/sites/reorder", [
            'sites' => [
                ['id' => $siteA, 'display_order' => 20],
                ['id' => $siteB, 'display_order' => 10],
            ],
        ])->assertOk();

        $this->assertSame(20, EventSite::query()->findOrFail($siteA)->display_order);
        $this->assertSame(10, EventSite::query()->findOrFail($siteB)->display_order);
    }
}
