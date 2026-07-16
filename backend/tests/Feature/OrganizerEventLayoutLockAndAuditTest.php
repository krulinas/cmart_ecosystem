<?php

namespace Tests\Feature;

use App\Models\EventLayoutAuditLog;
use App\Models\EventSite;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\Phase35EventLayoutFixtures;
use Tests\TestCase;

class OrganizerEventLayoutLockAndAuditTest extends TestCase
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

    public function test_row_create_writes_event_layout_audit_log(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $response = $this->postJson("/api/organizer/events/{$event->id}/layout/rows", [
            'label' => 'Audit Row',
            'vendor_category_id' => $this->foodCategory()->id,
        ]);

        $response->assertCreated();
        $rowId = (int) $response->json('row.id');

        $this->assertDatabaseHas('event_layout_audit_logs', [
            'carboot_event_id' => $event->id,
            'actor_user_id' => $organizer->id,
            'action' => EventLayoutAuditLog::ACTION_ROW_CREATED,
            'event_layout_row_id' => $rowId,
        ]);
    }

    public function test_site_create_writes_event_layout_audit_log(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'Audit Site Row']);

        $response = $this->postJson("/api/organizer/events/{$event->id}/layout/rows/{$row['id']}/sites", [
            'label' => 'AUD01',
            'space_id' => $this->standardSpace()->id,
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
        ]);

        $response->assertCreated();
        $siteId = (int) $response->json('site.id');
        $this->createdSiteIds[] = $siteId;

        $this->assertDatabaseHas('event_layout_audit_logs', [
            'carboot_event_id' => $event->id,
            'actor_user_id' => $organizer->id,
            'action' => EventLayoutAuditLog::ACTION_SITE_CREATED,
            'event_layout_row_id' => $row['id'],
            'event_site_id' => $siteId,
        ]);
    }

    public function test_row_locks_report_rename_locked_after_allocation(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);

        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'Lock Row']);
        $siteId = $this->createLayoutSiteViaApi($event, $row['id'], [
            'label' => 'L01',
            'position_number' => 1,
        ]);

        $this->getJson("/api/organizer/events/{$event->id}/layout")
            ->assertOk()
            ->assertJsonPath('rows.0.locks.rename_locked', false);

        $this->seedReleasedAllocation(
            $event,
            EventSite::query()->findOrFail($siteId),
            $day,
        );

        $this->getJson("/api/organizer/events/{$event->id}/layout")
            ->assertOk()
            ->assertJsonPath('rows.0.locks.rename_locked', true)
            ->assertJsonPath('rows.0.locks.category_change_locked', true)
            ->assertJsonPath('rows.0.locks.has_allocation_history', true);
    }
}
