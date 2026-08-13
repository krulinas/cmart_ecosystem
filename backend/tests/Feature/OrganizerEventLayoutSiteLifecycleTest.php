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

        $row = $this->createLayoutRowViaApi($event, ['label' => 'A']);

        $this->assertSame('A', $this->canonicalSite($event, 'A01')->row_label);

        $response = $this->postJson(
            "/api/organizer/events/{$event->id}/layout/rows/{$row['id']}/sites",
            array_merge($this->extraSitePayload($row['id']), [
                'space_id' => $this->standardSpace()->id,
            ]),
        );

        $response->assertCreated()
            ->assertJsonPath('site.row_label', 'A')
            ->assertJsonPath('site.label', 'A17');

        $siteId = (int) $response->json('site.id');
        $this->createdSiteIds[] = $siteId;
    }

    public function test_generate_sites_with_padding_creates_a01_through_a05(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $row = $this->createRowRecord($event, [
            'label' => 'A',
            'slug' => 'row-a-' . $event->id,
        ]);

        $response = $this->postJson(
            "/api/organizer/events/{$event->id}/layout/rows/{$row->id}/sites/generate",
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

        $row = $this->createLayoutRowViaApi($event, ['label' => 'A']);
        $beforeCount = EventSite::query()->forEvent($event->id)->count();
        $this->assertSame(16, $beforeCount);

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

        $sourceRow = $this->createLayoutRowViaApi($event, ['label' => 'A']);
        $targetRow = $this->createLayoutRowViaApi($event, ['label' => 'B']);
        $siteId = $this->createLayoutSiteViaApi($event, $sourceRow['id'], $this->extraSitePayload($sourceRow['id']));

        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$siteId}", [
            'event_layout_row_id' => $targetRow['id'],
        ])
            ->assertOk()
            ->assertJsonPath('site.row_label', 'B')
            ->assertJsonPath('site.event_layout_row_id', $targetRow['id']);
    }

    public function test_site_structure_locked_after_allocation_history(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $this->createLayoutRowViaApi($event, ['label' => 'A']);
        $site = $this->canonicalSite($event, 'A01');
        $this->seedReleasedAllocation($event, $site, $day);

        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$site->id}", [
            'label' => 'A99',
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

        $this->createLayoutRowViaApi($event, ['label' => 'A']);
        $this->createLayoutRowViaApi($event, ['label' => 'B']);
        $activeSite = $this->canonicalSite($event, 'A01');
        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$activeSite->id}", [
            'operational_status' => EventSite::STATUS_ACTIVE,
        ])->assertOk();
        $this->seedReservedAllocation($event, $activeSite->fresh(), $day);

        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$activeSite->id}", [
            'operational_status' => EventSite::STATUS_DISABLED,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'ACTIVE_ALLOCATIONS_PRESENT');

        $releasedSite = $this->canonicalSite($event, 'B01');
        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$releasedSite->id}", [
            'operational_status' => EventSite::STATUS_ACTIVE,
        ])->assertOk();
        $this->seedReleasedAllocation($event, $releasedSite->fresh(), $day);

        $this->patchJson("/api/organizer/events/{$event->id}/layout/sites/{$releasedSite->id}", [
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

        $historyRow = $this->createLayoutRowViaApi($event, ['label' => 'A']);
        $historySiteId = $this->createLayoutSiteViaApi(
            $event,
            $historyRow['id'],
            $this->extraSitePayload($historyRow['id']),
        );
        $this->seedReleasedAllocation(
            $event,
            EventSite::query()->findOrFail($historySiteId),
            $day,
        );

        $this->deleteJson("/api/organizer/events/{$event->id}/layout/sites/{$historySiteId}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'SITE_HAS_ALLOCATION_HISTORY');

        $canonical = $this->canonicalSite($event, 'A01');
        $this->deleteJson("/api/organizer/events/{$event->id}/layout/sites/{$canonical->id}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'CANONICAL_SITE_DELETE_FORBIDDEN');

        $cleanRow = $this->createLayoutRowViaApi($event, ['label' => 'B']);
        $cleanSiteId = $this->createLayoutSiteViaApi(
            $event,
            $cleanRow['id'],
            $this->extraSitePayload($cleanRow['id']),
        );

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

        $row = $this->createLayoutRowViaApi($event, ['label' => 'A']);
        $siteA = $this->canonicalSite($event, 'A01');
        $siteB = $this->canonicalSite($event, 'A02');

        $this->seedReleasedAllocation($event, $siteA, $day);

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$row['id']}/sites/reorder", [
            'sites' => [
                ['id' => $siteA->id, 'display_order' => 20],
                ['id' => $siteB->id, 'display_order' => 10],
            ],
        ])->assertOk();

        $this->assertSame(20, EventSite::query()->findOrFail($siteA->id)->display_order);
        $this->assertSame(10, EventSite::query()->findOrFail($siteB->id)->display_order);
    }
}
