<?php

namespace Tests\Feature;

use App\Models\BookingAuditLog;
use App\Models\BookingCategoryOverride;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventLayoutAuditLog;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\Phase35EventLayoutFixtures;
use Tests\TestCase;

class PublicEventLayoutTest extends TestCase
{
    use CleansUpTestFixtures;
    use Phase35EventLayoutFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function actor(string $role): User
    {
        return $this->trackUser(User::create([
            'name' => 'P310 ' . $role . ' ' . uniqid(),
            'email' => 'p310-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]));
    }

    /**
     * @return array{event: CarbootEvent, day: mixed, rows: array<string, EventLayoutRow>, sites: array<string, EventSite>}
     */
    private function seedPublishedLayout(array $eventOverrides = []): array
    {
        $event = $this->createEvent(array_merge([
            'title' => 'P310 Public Layout ' . uniqid(),
            'public_layout_published_at' => now()->subMinute(),
            'public_layout_entrance_note' => 'Masuk melalui pintu utama CMart.',
        ], $eventOverrides));
        $day = $this->createActiveDay($event);

        $thriftOne = $this->createRowRecord($event, [
            'vendor_category_id' => $this->thriftCategory()->id,
            'label' => 'Row A',
            'slug' => 'row-a-' . $event->id,
            'description' => 'Barangan pre-loved.',
            'display_order' => 1,
        ]);
        $food = $this->createRowRecord($event, [
            'vendor_category_id' => $this->foodCategory()->id,
            'label' => 'Row B',
            'slug' => 'row-b-' . $event->id,
            'description' => 'Makanan dan minuman.',
            'display_order' => 2,
        ]);
        $thriftTwo = $this->createRowRecord($event, [
            'vendor_category_id' => $this->thriftCategory()->id,
            'label' => 'Row C',
            'slug' => 'row-c-' . $event->id,
            'description' => 'Barangan rumah terpakai.',
            'display_order' => 3,
        ]);

        $sites = [
            'A01' => $this->createSiteRecord($event, $thriftOne, [
                'label' => 'A01', 'position_number' => 1, 'display_order' => 1,
            ]),
            'B02' => $this->createSiteRecord($event, $food, [
                'label' => 'B02', 'position_number' => 2, 'display_order' => 2,
            ]),
            'B01' => $this->createSiteRecord($event, $food, [
                'label' => 'B01', 'position_number' => 1, 'display_order' => 1,
            ]),
            'C01' => $this->createSiteRecord($event, $thriftTwo, [
                'label' => 'C01', 'position_number' => 1, 'display_order' => 1,
            ]),
        ];

        return [
            'event' => $event,
            'day' => $day,
            'rows' => ['A' => $thriftOne, 'B' => $food, 'C' => $thriftTwo],
            'sites' => $sites,
        ];
    }

    public function test_guest_and_every_authenticated_role_receive_the_same_public_projection(): void
    {
        $fixture = $this->seedPublishedLayout();
        $url = "/api/events/{$fixture['event']->id}/layout";

        $guest = $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('layout_available', true)
            ->assertJsonPath('published', true)
            ->assertJsonPath('historical', false)
            ->json();

        foreach (['community', 'organizer', 'cmart_management', 'super_admin'] as $role) {
            Sanctum::actingAs($this->actor($role));
            $this->assertSame($guest, $this->getJson($url)->assertOk()->json());
        }
    }

    public function test_projection_is_ordered_and_supports_multiple_rows_per_category(): void
    {
        $fixture = $this->seedPublishedLayout();
        $payload = $this->getJson("/api/events/{$fixture['event']->id}/layout")
            ->assertOk()
            ->json();

        $this->assertSame(['Pre-loved / Thrift', 'Food & Beverages'], array_column($payload['categories'], 'label'));
        $this->assertSame(2, $payload['categories'][0]['row_count']);
        $this->assertSame(['Row A', 'Row B', 'Row C'], array_column($payload['rows'], 'label'));
        $this->assertSame(['B01', 'B02'], array_column($payload['rows'][1]['sites'], 'label'));
    }

    public function test_hidden_inactive_archived_and_unresolved_records_are_excluded(): void
    {
        $fixture = $this->seedPublishedLayout();
        $event = $fixture['event'];

        $hidden = $this->createRowRecord($event, [
            'label' => 'Private Row',
            'slug' => 'private-' . $event->id,
            'display_order' => 4,
            'is_public' => false,
        ]);
        $this->createSiteRecord($event, $hidden, ['label' => 'P01']);

        $inactive = $this->createRowRecord($event, [
            'label' => 'Inactive Row',
            'slug' => 'inactive-' . $event->id,
            'display_order' => 5,
            'is_active' => false,
        ]);
        $this->createSiteRecord($event, $inactive, [
            'label' => 'I01',
            'operational_status' => EventSite::STATUS_DISABLED,
        ]);

        $archived = $this->createRowRecord($event, [
            'label' => 'Archived Row',
            'slug' => 'archived-' . $event->id,
            'display_order' => 6,
            'is_active' => false,
            'archived_at' => now(),
        ]);
        $this->createSiteRecord($event, $archived, [
            'label' => 'R01',
            'operational_status' => EventSite::STATUS_DISABLED,
        ]);

        $unresolved = $this->createSiteRecord($event, $fixture['rows']['A'], [
            'label' => 'U01',
            'event_layout_row_id' => null,
            'row_label' => 'Legacy',
            'position_number' => 99,
            'operational_status' => EventSite::STATUS_DISABLED,
        ]);

        $json = json_encode(
            $this->getJson("/api/events/{$event->id}/layout")->assertOk()->json(),
            JSON_THROW_ON_ERROR,
        );
        $this->assertStringNotContainsString('Private Row', $json);
        $this->assertStringNotContainsString('Inactive Row', $json);
        $this->assertStringNotContainsString('Archived Row', $json);
        $this->assertStringNotContainsString($unresolved->label, $json);
    }

    public function test_non_public_or_inactive_category_makes_public_layout_unavailable(): void
    {
        foreach ([
            ['is_public' => false],
            ['is_active' => false],
        ] as $categoryMutation) {
            $fixture = $this->seedPublishedLayout();
            $category = $this->foodCategory();
            $original = [
                'is_public' => $category->is_public,
                'is_active' => $category->is_active,
            ];

            try {
                $category->update($categoryMutation);
                $this->getJson("/api/events/{$fixture['event']->id}/layout")
                    ->assertNotFound()
                    ->assertJsonPath('error', 'PUBLIC_LAYOUT_NOT_AVAILABLE')
                    ->assertJsonMissingPath('blocking_reasons');
            } finally {
                $category->update($original);
                $this->cleanupTrackedFixtures();
            }
        }
    }

    public function test_unpublished_empty_and_deleted_events_return_safe_not_found_responses(): void
    {
        $unpublished = $this->createEvent(['public_layout_published_at' => null]);
        $this->getJson("/api/events/{$unpublished->id}/layout")
            ->assertNotFound()
            ->assertJsonPath('layout_available', false)
            ->assertJsonPath('error', 'PUBLIC_LAYOUT_NOT_AVAILABLE')
            ->assertJsonMissingPath('blocking_reasons');

        $empty = $this->createEvent(['public_layout_published_at' => now()]);
        $this->getJson("/api/events/{$empty->id}/layout")
            ->assertNotFound()
            ->assertJsonPath('error', 'PUBLIC_LAYOUT_NOT_AVAILABLE');

        $deletedId = $this->createEvent()->id;
        CarbootEvent::query()->whereKey($deletedId)->delete();
        $this->createdEventIds = array_values(array_diff($this->createdEventIds, [$deletedId]));
        $this->getJson("/api/events/{$deletedId}/layout")
            ->assertNotFound()
            ->assertJsonPath('error', 'PUBLIC_EVENT_NOT_FOUND');
    }

    public function test_upcoming_active_ended_and_closed_lifecycle_matches_adr(): void
    {
        $upcoming = $this->seedPublishedLayout();
        $this->getJson("/api/events/{$upcoming['event']->id}/layout")
            ->assertOk()
            ->assertJsonPath('historical', false);

        $active = $this->seedPublishedLayout([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
        $this->getJson("/api/events/{$active['event']->id}/layout")
            ->assertOk()
            ->assertJsonPath('historical', false);

        $ended = $this->seedPublishedLayout([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $this->getJson("/api/events/{$ended['event']->id}/layout")
            ->assertOk()
            ->assertJsonPath('historical', true);

        $closed = $this->seedPublishedLayout(['status' => 'Closed']);
        $this->getJson("/api/events/{$closed['event']->id}/layout")
            ->assertOk()
            ->assertJsonPath('historical', true);
    }

    public function test_booking_payment_allocation_override_and_audit_data_never_leak(): void
    {
        $fixture = $this->seedPublishedLayout();
        $vendor = $this->actor('community');
        $organizer = $this->actor('organizer');
        $booking = $this->createBookingForSite(
            $fixture['event'],
            $fixture['sites']['B01'],
            $fixture['day'],
            $vendor,
        );
        $booking->update(['category_label_snapshot' => 'Food & Beverages']);
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 30,
            'payment_status' => 'Paid',
        ]);
        $this->createdInvoiceIds[] = $invoice->id;
        $allocation = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $fixture['day']->id,
            'event_site_id' => $fixture['sites']['B01']->id,
            'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
            'reserved_at' => now(),
            'confirmed_at' => now(),
            'active_lock' => 1,
        ]);
        $this->createdAllocationIds[] = $allocation->id;
        BookingCategoryOverride::create([
            'booking_id' => $booking->id,
            'booking_category_id_snapshot' => $this->foodCategory()->id,
            'booking_category_label_snapshot' => 'Food & Beverages',
            'assigned_category_id_snapshot' => $this->thriftCategory()->id,
            'assigned_category_label_snapshot' => 'Pre-loved / Thrift',
            'assigned_row_ids_snapshot' => [$fixture['rows']['A']->id],
            'assigned_row_labels_snapshot' => ['Row A'],
            'assigned_site_ids_snapshot' => [$fixture['sites']['A01']->id],
            'assigned_site_labels_snapshot' => ['A01'],
            'reason' => 'Private override reason must never be public.',
            'applied_by_user_id' => $organizer->id,
            'applied_at' => now(),
            'status' => BookingCategoryOverride::STATUS_ACTIVE,
            'active_lock' => 1,
        ]);
        BookingAuditLog::create([
            'booking_id' => $booking->id,
            'actor_user_id' => $organizer->id,
            'action' => 'private_audit_action',
            'from_status' => 'Approved',
            'to_status' => 'Approved',
            'revision_comment' => 'Private audit reason.',
        ]);

        $payload = $this->getJson("/api/events/{$fixture['event']->id}/layout")
            ->assertOk()
            ->json();
        $json = strtolower(json_encode($payload, JSON_THROW_ON_ERROR));

        foreach ([
            'booking', 'invoice', 'payment', 'reserved', 'confirmed',
            'allocation', 'override', 'reason', 'actor', 'audit', 'lock',
            'vendor', 'email', 'phone', 'private override',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
        $this->assertStringContainsString('b01', $json);
    }

    public function test_publish_requires_public_readiness_and_unpublish_hides_layout(): void
    {
        $organizer = $this->actor('organizer');
        Sanctum::actingAs($organizer);
        $event = $this->createEvent();

        $this->postJson("/api/organizer/events/{$event->id}/layout/publish")
            ->assertStatus(422)
            ->assertJsonPath('error', 'PUBLIC_LAYOUT_NOT_PUBLISHABLE');

        $fixture = $this->seedPublishedLayout(['public_layout_published_at' => null]);
        $this->postJson("/api/organizer/events/{$fixture['event']->id}/layout/publish", [
            'entrance_note' => 'Pintu masuk utama.',
        ])
            ->assertOk()
            ->assertJsonPath('publication.published', true);

        $this->getJson("/api/events/{$fixture['event']->id}/layout")->assertOk();

        $this->postJson("/api/organizer/events/{$fixture['event']->id}/layout/unpublish")
            ->assertOk()
            ->assertJsonPath('publication.published', false);
        $this->getJson("/api/events/{$fixture['event']->id}/layout")
            ->assertNotFound()
            ->assertJsonPath('error', 'PUBLIC_LAYOUT_NOT_AVAILABLE');

        $this->assertSame(
            1,
            EventLayoutAuditLog::query()
                ->where('carboot_event_id', $fixture['event']->id)
                ->where('action', EventLayoutAuditLog::ACTION_LAYOUT_PUBLISHED)
                ->count(),
        );
        $this->assertSame(
            1,
            EventLayoutAuditLog::query()
                ->where('carboot_event_id', $fixture['event']->id)
                ->where('action', EventLayoutAuditLog::ACTION_LAYOUT_UNPUBLISHED)
                ->count(),
        );
    }
}
