<?php

namespace Tests\Feature;

use App\Models\EventSite;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\Phase35EventLayoutFixtures;
use Tests\TestCase;

class OrganizerEventLayoutReadinessTest extends TestCase
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

    /**
     * @return list<string>
     */
    private function blockerCodes(array $payload): array
    {
        return array_column($payload['blocking_reasons'] ?? [], 'code');
    }

    public function test_readiness_reports_no_active_event_days(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));
        $event = $this->createEvent();

        $response = $this->getJson("/api/organizer/events/{$event->id}/layout/readiness");

        $response->assertOk()
            ->assertJsonPath('operational_ready', false)
            ->assertJsonPath('public_ready', false);

        $this->assertContains('NO_ACTIVE_EVENT_DAYS', $this->blockerCodes($response->json()));
    }

    public function test_readiness_reports_no_active_layout_rows(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));
        $event = $this->createEvent();
        $this->createActiveDay($event);

        $response = $this->getJson("/api/organizer/events/{$event->id}/layout/readiness");

        $response->assertOk()
            ->assertJsonPath('operational_ready', false);

        $this->assertContains('NO_ACTIVE_LAYOUT_ROWS', $this->blockerCodes($response->json()));
    }

    public function test_readiness_reports_active_row_missing_category(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));
        $event = $this->createEvent();
        $this->createActiveDay($event);

        $this->createRowRecord($event, [
            'label' => 'Missing Category Row',
            'slug' => 'missing-category-row',
            'vendor_category_id' => null,
        ]);

        $response = $this->getJson("/api/organizer/events/{$event->id}/layout/readiness");

        $this->assertContains('ACTIVE_ROW_MISSING_CATEGORY', $this->blockerCodes($response->json()));
    }

    public function test_readiness_reports_row_category_inactive(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));
        $event = $this->createEvent();
        $this->createActiveDay($event);
        $category = $this->foodCategory();
        $originalActive = $category->is_active;

        try {
            $category->update(['is_active' => false]);

            $this->createRowRecord($event, [
                'label' => 'Inactive Category Row',
                'slug' => 'inactive-category-row',
                'vendor_category_id' => $category->id,
            ]);

            $response = $this->getJson("/api/organizer/events/{$event->id}/layout/readiness");

            $this->assertContains('ROW_CATEGORY_INACTIVE', $this->blockerCodes($response->json()));
        } finally {
            $category->update(['is_active' => $originalActive]);
        }

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_readiness_reports_active_row_has_no_active_sites(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));
        $event = $this->createEvent();
        $this->createActiveDay($event);

        $row = $this->createRowRecord($event, [
            'label' => 'No Sites Row',
            'slug' => 'no-sites-row',
        ]);

        $this->createSiteRecord($event, $row, [
            'label' => 'NS01',
            'operational_status' => EventSite::STATUS_DISABLED,
        ]);

        $response = $this->getJson("/api/organizer/events/{$event->id}/layout/readiness");

        $this->assertContains('ACTIVE_ROW_HAS_NO_ACTIVE_SITES', $this->blockerCodes($response->json()));
    }

    public function test_readiness_reports_active_site_missing_row(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));
        $event = $this->createEvent();
        $this->createActiveDay($event);

        $row = $this->createRowRecord($event, [
            'label' => 'Parent Row',
            'slug' => 'parent-row',
        ]);
        $this->createSiteRecord($event, $row, [
            'label' => 'MR01',
            'event_layout_row_id' => null,
            'row_label' => 'Legacy',
        ]);

        $response = $this->getJson("/api/organizer/events/{$event->id}/layout/readiness");

        $this->assertContains('ACTIVE_SITE_MISSING_ROW', $this->blockerCodes($response->json()));
    }

    public function test_readiness_reports_fully_ready_fixture(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));
        $event = $this->createEvent();
        $this->createActiveDay($event);

        $row = $this->createRowRecord($event, [
            'label' => 'Ready Row',
            'slug' => 'ready-row',
            'is_active' => true,
            'is_public' => true,
        ]);
        $this->createSiteRecord($event, $row, [
            'label' => 'RD01',
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);

        $response = $this->getJson("/api/organizer/events/{$event->id}/layout/readiness");

        $response->assertOk()
            ->assertJsonPath('operational_ready', true)
            ->assertJsonPath('public_ready', true)
            ->assertJsonPath('blocking_reasons', []);
    }
}
