<?php

namespace Tests\Feature;

use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\User;
use App\Models\VendorCategory;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\Phase35EventLayoutFixtures;
use Tests\TestCase;

class OrganizerEventLayoutRowLifecycleTest extends TestCase
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

    public function test_create_row_with_category(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $category = $this->foodCategory();

        Sanctum::actingAs($organizer);

        $response = $this->postJson("/api/organizer/events/{$event->id}/layout/rows", [
            'label' => 'Food Lane',
            'vendor_category_id' => $category->id,
            'description' => 'Primary food row',
        ]);

        $response->assertCreated()
            ->assertJsonPath('row.label', 'Food Lane')
            ->assertJsonPath('row.category.slug', 'food-beverages')
            ->assertJsonPath('row.locks.rename_locked', false);
    }

    public function test_inactive_category_rejected_on_create(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $category = $this->foodCategory();
        $originalActive = $category->is_active;

        try {
            $category->update(['is_active' => false]);

            Sanctum::actingAs($organizer);

            $this->postJson("/api/organizer/events/{$event->id}/layout/rows", [
                'label' => 'Blocked Row',
                'vendor_category_id' => $category->id,
            ])
                ->assertStatus(422)
                ->assertJsonPath('error', 'CATEGORY_INACTIVE');
        } finally {
            $category->update(['is_active' => $originalActive]);
        }

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_duplicate_row_label_returns_conflict(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $category = $this->foodCategory();

        Sanctum::actingAs($organizer);

        $this->postJson("/api/organizer/events/{$event->id}/layout/rows", [
            'label' => 'Duplicate Lane',
            'vendor_category_id' => $category->id,
        ])->assertCreated();

        $this->postJson("/api/organizer/events/{$event->id}/layout/rows", [
            'label' => 'Duplicate Lane',
            'vendor_category_id' => $category->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'ROW_LABEL_CONFLICT');
    }

    public function test_row_slug_remains_stable_after_rename_and_updates_site_row_label(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $created = $this->createLayoutRowViaApi($event, ['label' => 'Alpha Lane']);
        $rowId = $created['id'];
        $originalSlug = $created['slug'];

        $siteId = $this->createLayoutSiteViaApi($event, $rowId, [
            'label' => 'A01',
            'position_number' => 1,
        ]);

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$rowId}", [
            'label' => 'Beta Lane',
        ])
            ->assertOk()
            ->assertJsonPath('row.label', 'Beta Lane')
            ->assertJsonPath('row.slug', $originalSlug);

        $this->assertSame('Beta Lane', EventSite::query()->findOrFail($siteId)->row_label);
    }

    public function test_rename_and_category_change_blocked_after_allocation_history(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'History Row']);
        $siteId = $this->createLayoutSiteViaApi($event, $row['id'], [
            'label' => 'H01',
            'position_number' => 1,
        ]);
        $site = EventSite::query()->findOrFail($siteId);

        $this->seedReleasedAllocation($event, $site, $day);

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$row['id']}", [
            'label' => 'Renamed History Row',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'ROW_LABEL_LOCKED');

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$row['id']}", [
            'vendor_category_id' => $this->thriftCategory()->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'ROW_CATEGORY_LOCKED');
    }

    public function test_description_order_and_public_allowed_after_allocation_history(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'Mutable Row']);
        $siteId = $this->createLayoutSiteViaApi($event, $row['id'], [
            'label' => 'M01',
            'position_number' => 1,
        ]);
        $site = EventSite::query()->findOrFail($siteId);
        $this->seedReleasedAllocation($event, $site, $day);

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$row['id']}", [
            'description' => 'Updated after history',
            'display_order' => 99,
            'is_public' => false,
        ])
            ->assertOk()
            ->assertJsonPath('row.description', 'Updated after history')
            ->assertJsonPath('row.display_order', 99)
            ->assertJsonPath('row.is_public', false);
    }

    public function test_empty_row_can_be_deleted_but_nonempty_row_cannot(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $empty = $this->createLayoutRowViaApi($event, ['label' => 'Empty Row']);

        $this->deleteJson("/api/organizer/events/{$event->id}/layout/rows/{$empty['id']}")
            ->assertOk();

        $this->assertNull(EventLayoutRow::query()->find($empty['id']));

        $nonEmpty = $this->createLayoutRowViaApi($event, ['label' => 'Non Empty Row']);
        $this->createLayoutSiteViaApi($event, $nonEmpty['id'], [
            'label' => 'N01',
            'position_number' => 1,
        ]);

        $this->deleteJson("/api/organizer/events/{$event->id}/layout/rows/{$nonEmpty['id']}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'ROW_NOT_EMPTY');
    }

    public function test_archive_blocked_with_reserved_allowed_with_released_and_disables_sites(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $reservedRow = $this->createLayoutRowViaApi($event, ['label' => 'ResvArch']);
        $reservedSiteId = $this->createLayoutSiteViaApi($event, $reservedRow['id'], [
            'label' => 'R01',
            'position_number' => 1,
        ]);
        $this->seedReservedAllocation(
            $event,
            EventSite::query()->findOrFail($reservedSiteId),
            $day,
        );

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$reservedRow['id']}/archive")
            ->assertStatus(409)
            ->assertJsonPath('error', 'ACTIVE_ALLOCATIONS_PRESENT');

        $releasedRow = $this->createLayoutRowViaApi($event, ['label' => 'RelArch']);
        $releasedSiteId = $this->createLayoutSiteViaApi($event, $releasedRow['id'], [
            'label' => 'R02',
            'position_number' => 1,
        ]);
        $this->seedReleasedAllocation(
            $event,
            EventSite::query()->findOrFail($releasedSiteId),
            $day,
        );

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$releasedRow['id']}/archive")
            ->assertOk()
            ->assertJsonPath('row.is_active', false)
            ->assertJsonPath('row.is_public', false);

        $this->assertSame(
            EventSite::STATUS_DISABLED,
            EventSite::query()->findOrFail($releasedSiteId)->operational_status,
        );
    }

    public function test_unarchive_does_not_reactivate_sites_and_sets_is_public_false(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'Unarchive Row']);
        $siteId = $this->createLayoutSiteViaApi($event, $row['id'], [
            'label' => 'U01',
            'position_number' => 1,
        ]);
        $this->seedReleasedAllocation(
            $event,
            EventSite::query()->findOrFail($siteId),
            $day,
        );

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$row['id']}/archive")
            ->assertOk();

        $this->patchJson("/api/organizer/events/{$event->id}/layout/rows/{$row['id']}/unarchive")
            ->assertOk()
            ->assertJsonPath('row.is_active', true)
            ->assertJsonPath('row.is_public', false);

        $this->assertSame(
            EventSite::STATUS_DISABLED,
            EventSite::query()->findOrFail($siteId)->operational_status,
        );
    }
}
