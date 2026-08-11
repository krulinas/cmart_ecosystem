<?php

namespace Tests\Feature;

use App\Models\EventLayoutAuditLog;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\Phase35EventLayoutFixtures;
use Tests\TestCase;

class StandardParkingLayoutGeneratorTest extends TestCase
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
            'name' => 'StdLayout '.$role.' '.uniqid(),
            'email' => 'std-layout-'.$role.'-'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]));
    }

    /**
     * @return array{space_id: int, row_categories: array{A: int, B: int, C: int, D: int}}
     */
    private function standardPayload(): array
    {
        $food = $this->foodCategory()->id;
        $thrift = $this->thriftCategory()->id;

        return [
            'space_id' => $this->standardSpace()->id,
            'row_categories' => [
                'A' => $food,
                'B' => $food,
                'C' => $thrift,
                'D' => $thrift,
            ],
        ];
    }

    public function test_standard_template_creates_exactly_four_rows_and_sixty_four_sites(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        Sanctum::actingAs($organizer);

        $response = $this->postJson(
            "/api/organizer/events/{$event->id}/layout/standard-template",
            $this->standardPayload(),
        );

        $response->assertCreated()
            ->assertJsonPath('rows_created', 4)
            ->assertJsonPath('sites_created', 64)
            ->assertJsonPath('row_labels', ['A', 'B', 'C', 'D'])
            ->assertJsonPath('site_labels.0', 'A01')
            ->assertJsonPath('site_labels.15', 'A16')
            ->assertJsonPath('site_labels.16', 'B01')
            ->assertJsonPath('site_labels.63', 'D16');

        $this->assertSame(4, EventLayoutRow::query()->forEvent($event->id)->count());
        $this->assertSame(64, EventSite::query()->forEvent($event->id)->count());

        $siteIds = EventSite::query()->forEvent($event->id)->pluck('id')->all();
        $this->createdSiteIds = array_merge($this->createdSiteIds, $siteIds);

        $rowA = EventLayoutRow::query()->forEvent($event->id)->where('label', 'A')->firstOrFail();
        $this->assertSame($this->foodCategory()->id, (int) $rowA->vendor_category_id);

        $a01 = EventSite::query()->forEvent($event->id)->where('label', 'A01')->firstOrFail();
        $this->assertSame((int) $rowA->id, (int) $a01->event_layout_row_id);
        $this->assertSame('A', $a01->row_label);
        $this->assertSame(1, (int) $a01->position_number);
        $this->assertSame(1, (int) $a01->grid_row);
        $this->assertSame(1, (int) $a01->grid_column);

        $d16 = EventSite::query()->forEvent($event->id)->where('label', 'D16')->firstOrFail();
        $this->assertSame(16, (int) $d16->position_number);
        $this->assertSame(4, (int) $d16->grid_row);
        $this->assertSame(16, (int) $d16->grid_column);

        $this->assertDatabaseHas('event_layout_audit_logs', [
            'carboot_event_id' => $event->id,
            'action' => EventLayoutAuditLog::ACTION_STANDARD_TEMPLATE_GENERATED,
        ]);
    }

    public function test_standard_template_works_without_client_space_id(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        Sanctum::actingAs($organizer);

        $payload = $this->standardPayload();
        unset($payload['space_id']);

        $response = $this->postJson(
            "/api/organizer/events/{$event->id}/layout/standard-template",
            $payload,
        );

        $response->assertCreated()
            ->assertJsonPath('rows_created', 4)
            ->assertJsonPath('sites_created', 64);

        $siteIds = EventSite::query()->forEvent($event->id)->pluck('id')->all();
        $this->createdSiteIds = array_merge($this->createdSiteIds, $siteIds);

        $layout = $this->getJson("/api/organizer/events/{$event->id}/layout")->assertOk()->json();
        $firstSite = $layout['rows'][0]['sites'][0] ?? null;
        $this->assertIsArray($firstSite);
        $this->assertArrayHasKey('space', $firstSite);
        $this->assertArrayNotHasKey('price', $firstSite['space'] ?? []);
        $this->assertSame(\App\Models\Space::PHYSICAL_PARKING_SITE, $firstSite['space']['space_size'] ?? null);
    }

    public function test_standard_template_is_blocked_when_layout_already_exists(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        Sanctum::actingAs($organizer);

        $this->postJson(
            "/api/organizer/events/{$event->id}/layout/standard-template",
            $this->standardPayload(),
        )->assertCreated();

        $siteIds = EventSite::query()->forEvent($event->id)->pluck('id')->all();
        $this->createdSiteIds = array_merge($this->createdSiteIds, $siteIds);

        $this->postJson(
            "/api/organizer/events/{$event->id}/layout/standard-template",
            $this->standardPayload(),
        )
            ->assertStatus(409)
            ->assertJsonPath('error', 'LAYOUT_ALREADY_EXISTS');

        $this->assertSame(4, EventLayoutRow::query()->forEvent($event->id)->count());
        $this->assertSame(64, EventSite::query()->forEvent($event->id)->count());
    }

    public function test_standard_template_is_blocked_when_public_layout_is_published(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent([
            'public_layout_published_at' => now(),
            'public_layout_entrance_note' => 'Enter from gate B',
        ]);
        Sanctum::actingAs($organizer);

        $this->postJson(
            "/api/organizer/events/{$event->id}/layout/standard-template",
            $this->standardPayload(),
        )
            ->assertStatus(409)
            ->assertJsonPath('error', 'PUBLIC_LAYOUT_PUBLISHED');

        $this->assertSame(0, EventLayoutRow::query()->forEvent($event->id)->count());
        $this->assertSame(0, EventSite::query()->forEvent($event->id)->count());
    }

    public function test_standard_template_works_without_active_event_days_and_blocks_vendor_availability(): void
    {
        $organizer = $this->createUser('organizer');
        $vendor = $this->createUser('community');
        $event = $this->createEvent();
        Sanctum::actingAs($organizer);

        $this->postJson(
            "/api/organizer/events/{$event->id}/layout/standard-template",
            $this->standardPayload(),
        )->assertCreated();

        $siteIds = EventSite::query()->forEvent($event->id)->pluck('id')->all();
        $this->createdSiteIds = array_merge($this->createdSiteIds, $siteIds);

        $layout = $this->getJson("/api/organizer/events/{$event->id}/layout");
        $layout->assertOk()
            ->assertJsonCount(4, 'rows')
            ->assertJsonPath('readiness.operational_ready', false);

        $blockingCodes = collect($layout->json('readiness.blocking_reasons'))
            ->pluck('code')
            ->all();
        $this->assertContains('NO_ACTIVE_EVENT_DAYS', $blockingCodes);

        Sanctum::actingAs($vendor);
        $this->getJson(
            "/api/vendor/events/{$event->id}/site-availability?vendor_category_id=".$this->foodCategory()->id,
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_active_event_days');
    }

    public function test_standard_template_rejects_inactive_category(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        Sanctum::actingAs($organizer);

        $category = $this->foodCategory();
        $originalActive = $category->is_active;
        $category->is_active = false;
        $category->save();

        try {
            $payload = $this->standardPayload();
            $payload['row_categories']['A'] = $category->id;

            $this->postJson(
                "/api/organizer/events/{$event->id}/layout/standard-template",
                $payload,
            )
                ->assertStatus(422)
                ->assertJsonPath('error', 'CATEGORY_INACTIVE');

            $this->assertSame(0, EventLayoutRow::query()->forEvent($event->id)->count());
        } finally {
            $category->is_active = $originalActive;
            $category->save();
        }
    }

    public function test_allocated_layout_cannot_be_destructively_replaced_by_standard_template(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $day = $this->createActiveDay($event);
        Sanctum::actingAs($organizer);

        $row = $this->createLayoutRowViaApi($event, ['label' => 'A']);
        $sites = EventSite::query()->forEvent($event->id)->get();
        $this->createdSiteIds = array_merge($this->createdSiteIds, $sites->pluck('id')->all());
        $site = $sites->firstWhere('label', 'A01');
        $this->assertNotNull($site);
        $this->seedReleasedAllocation($event, $site, $day);

        $this->postJson(
            "/api/organizer/events/{$event->id}/layout/standard-template",
            $this->standardPayload(),
        )
            ->assertStatus(409)
            ->assertJsonPath('error', 'LAYOUT_ALREADY_EXISTS');

        $this->assertSame(1, EventLayoutRow::query()->forEvent($event->id)->count());
        $this->assertSame(16, EventSite::query()->forEvent($event->id)->count());
    }
}
