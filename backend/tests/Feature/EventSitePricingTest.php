<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorCategory;
use App\Services\BookingAllocationReservationService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\TestCase;

class EventSitePricingTest extends TestCase
{
    use CleansUpTestFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function createOrganizer(): User
    {
        $user = User::create([
            'name' => 'Pricing Organizer '.uniqid(),
            'email' => 'pricing-org-'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'organizer',
            'vendor_status' => 'none',
            'default_site_price' => null,
        ]);

        return $this->trackUser($user);
    }

    private function createVendor(): User
    {
        $user = User::create([
            'name' => 'Pricing Vendor '.uniqid(),
            'email' => 'pricing-vendor-'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'approved',
        ]);

        return $this->trackUser($user);
    }

    private function space(): Space
    {
        return Space::defaultPhysical();
    }

    private function foodCategory(): VendorCategory
    {
        return VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
    }

    private function createEvent(User $organizer, array $overrides = []): CarbootEvent
    {
        $starts = now()->addDays(15)->setTime(8, 0, 0);

        $event = CarbootEvent::create(array_merge([
            'title' => 'Pricing Event '.uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addDays(1)->setTime(17, 0, 0),
            'status' => 'Available',
            'description' => 'Event-level pricing test',
            'max_slots' => 50,
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
            'site_price' => '20.00',
            'vendor_site_open_limit' => 3,
        ], $overrides));

        return $this->trackEvent($event);
    }

    private function ensureDays(CarbootEvent $event, int $count = 2): void
    {
        for ($i = 0; $i < $count; $i++) {
            $date = $event->starts_at->copy()->addDays($i)->toDateString();
            $day = EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $date,
                'starts_at' => $date.' 08:00:00',
                'ends_at' => $date.' 17:00:00',
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => $i + 1,
            ]);
            $this->createdDayIds[] = $day->id;
        }
    }

    private function createSites(CarbootEvent $event, int $count = 3): array
    {
        if ($event->vendor_site_open_limit === null || (int) $event->vendor_site_open_limit !== $count) {
            $event->forceFill(['vendor_site_open_limit' => $count])->save();
        }
        $category = $this->foodCategory();
        $space = $this->space();
        $row = EventLayoutRow::query()->firstOrCreate(
            [
                'carboot_event_id' => $event->id,
                'label' => 'A',
            ],
            [
                'vendor_category_id' => $category->id,
                'slug' => 'pricing-a-'.$event->id,
                'display_order' => 1,
                'is_active' => true,
                'is_public' => true,
            ],
        );

        $sites = [];
        for ($i = 1; $i <= $count; $i++) {
            $label = 'A'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $site = EventSite::create([
                'carboot_event_id' => $event->id,
                'event_layout_row_id' => $row->id,
                'space_id' => $space->id,
                'label' => $label,
                'row_label' => 'A',
                'position_number' => $i,
                'grid_row' => 1,
                'grid_column' => $i,
                'display_order' => $i,
                'operational_status' => EventSite::STATUS_ACTIVE,
            ]);
            $this->createdSiteIds[] = $site->id;
            $sites[] = $site;
        }

        return $sites;
    }

    private function bookingPayload(CarbootEvent $event, array $siteIds, array $overrides = []): array
    {
        return array_merge([
            'event_id' => $event->id,
            'event_site_ids' => $siteIds,
            'vendor_category_id' => $this->foodCategory()->id,
            'product_details' => 'Event site pricing regression fixture with enough product detail text.',
        ], $overrides);
    }

    public function test_organizer_can_create_event_with_site_price(): void
    {
        $organizer = $this->createOrganizer();
        Sanctum::actingAs($organizer);

        $starts = now()->addDays(20)->setTime(8, 0)->format('Y-m-d H:i:s');
        $ends = now()->addDays(20)->setTime(17, 0)->format('Y-m-d H:i:s');

        $response = $this->postJson('/api/carboot-events', [
            'title' => 'Priced Event '.uniqid(),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => 'Available',
            'site_price' => '20.00',
        ])->assertCreated();

        $eventId = (int) $response->json('event.id');
        $this->createdEventIds[] = $eventId;

        $this->assertDatabaseHas('carboot_events', [
            'id' => $eventId,
            'site_price' => '20.00',
        ]);
    }

    public function test_event_creation_rejects_missing_and_non_positive_site_price(): void
    {
        $organizer = $this->createOrganizer();
        Sanctum::actingAs($organizer);

        $starts = now()->addDays(21)->setTime(8, 0)->format('Y-m-d H:i:s');
        $ends = now()->addDays(21)->setTime(17, 0)->format('Y-m-d H:i:s');
        $base = [
            'title' => 'Invalid Price Event '.uniqid(),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => 'Available',
        ];

        $this->postJson('/api/carboot-events', $base)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['site_price']);

        $this->postJson('/api/carboot-events', array_merge($base, ['site_price' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['site_price']);

        $this->postJson('/api/carboot-events', array_merge($base, ['site_price' => -5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['site_price']);
    }

    public function test_save_as_default_updates_organizer_default_and_prefills_next_create_via_auth(): void
    {
        $organizer = $this->createOrganizer();
        Sanctum::actingAs($organizer);

        $starts = now()->addDays(22)->setTime(8, 0)->format('Y-m-d H:i:s');
        $ends = now()->addDays(22)->setTime(17, 0)->format('Y-m-d H:i:s');

        $created = $this->postJson('/api/carboot-events', [
            'title' => 'Default Price Event '.uniqid(),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => 'Available',
            'site_price' => '25.50',
            'save_as_default_site_price' => true,
        ])->assertCreated();

        $this->createdEventIds[] = (int) $created->json('event.id');

        $this->assertSame('25.50', number_format((float) $organizer->fresh()->default_site_price, 2, '.', ''));

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.default_site_price', '25.50');
    }

    public function test_saving_without_default_checkbox_does_not_overwrite_organizer_default(): void
    {
        $organizer = $this->createOrganizer();
        $organizer->forceFill(['default_site_price' => '18.00'])->save();
        Sanctum::actingAs($organizer);

        $starts = now()->addDays(23)->setTime(8, 0)->format('Y-m-d H:i:s');
        $ends = now()->addDays(23)->setTime(17, 0)->format('Y-m-d H:i:s');

        $created = $this->postJson('/api/carboot-events', [
            'title' => 'No Default Overwrite '.uniqid(),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => 'Available',
            'site_price' => '33.00',
            'save_as_default_site_price' => false,
        ])->assertCreated();

        $this->createdEventIds[] = (int) $created->json('event.id');

        $this->assertSame('18.00', number_format((float) $organizer->fresh()->default_site_price, 2, '.', ''));
        $this->assertDatabaseHas('carboot_events', [
            'id' => (int) $created->json('event.id'),
            'site_price' => '33.00',
        ]);
    }

    public function test_booking_totals_use_event_site_price_times_site_count_not_days_or_space_price(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createEvent($this->createOrganizer(), ['site_price' => '20.00']);
        $sites = $this->createSites($event, 3);
        $this->ensureDays($event, 3);
        Sanctum::actingAs($vendor);

        $one = $this->postJson('/api/bookings', $this->bookingPayload($event, [$sites[0]->id]))
            ->assertCreated()
            ->assertJsonPath('invoice.amount', '20.00')
            ->json();
        $this->createdBookingIds[] = (int) $one['booking']['id'];

        $two = $this->postJson('/api/bookings', $this->bookingPayload($event, [$sites[1]->id, $sites[2]->id]))
            ->assertCreated()
            ->assertJsonPath('invoice.amount', '40.00')
            ->json();
        $this->createdBookingIds[] = (int) $two['booking']['id'];

        $bookingTwo = Booking::findOrFail((int) $two['booking']['id']);
        $this->assertSame('20.00', number_format((float) $bookingTwo->unit_site_price, 2, '.', ''));
        $this->assertSame(2, (int) $bookingTwo->site_quantity);
        $this->assertSame(6, BookingDayAllocation::where('booking_id', $bookingTwo->id)->count());
    }

    public function test_three_sites_total_sixty_and_client_amount_fields_are_rejected(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createEvent($this->createOrganizer(), ['site_price' => '20.00']);
        $sites = $this->createSites($event, 3);
        $this->ensureDays($event, 2);
        Sanctum::actingAs($vendor);

        $this->postJson('/api/bookings', $this->bookingPayload($event, [
            $sites[0]->id,
            $sites[1]->id,
            $sites[2]->id,
        ], [
            'amount' => 1,
            'total' => 1,
            'invoice_amount' => 1,
            'unit_site_price' => 1,
            'site_quantity' => 99,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'amount',
                'total',
                'invoice_amount',
                'unit_site_price',
                'site_quantity',
            ]);

        $ok = $this->postJson('/api/bookings', $this->bookingPayload($event, [
            $sites[0]->id,
            $sites[1]->id,
            $sites[2]->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('invoice.amount', '60.00')
            ->json();

        $this->createdBookingIds[] = (int) $ok['booking']['id'];
        $booking = Booking::findOrFail((int) $ok['booking']['id']);
        $this->assertSame(3, (int) $booking->site_quantity);
        $this->assertSame('20.00', number_format((float) $booking->unit_site_price, 2, '.', ''));
    }

    public function test_missing_event_site_price_fails_safely(): void
    {
        $event = new CarbootEvent(['site_price' => null]);
        $service = app(BookingAllocationReservationService::class);
        $method = new \ReflectionMethod(BookingAllocationReservationService::class, 'resolveEventUnitPrice');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $event);
            $this->fail('Expected AllocationValidationException for missing event site price.');
        } catch (\App\Exceptions\AllocationValidationException $exception) {
            $this->assertSame('missing_event_site_price', $exception->error);
        }

        $event->site_price = '0.00';
        try {
            $method->invoke($service, $event);
            $this->fail('Expected AllocationValidationException for zero event site price.');
        } catch (\App\Exceptions\AllocationValidationException $exception) {
            $this->assertSame('missing_event_site_price', $exception->error);
        }
    }

    public function test_existing_booking_amount_unchanged_after_event_price_change_new_booking_uses_new_price(): void
    {
        $organizer = $this->createOrganizer();
        $vendor = $this->createVendor();
        $event = $this->createEvent($organizer, ['site_price' => '20.00']);
        $sites = $this->createSites($event, 3);
        $this->ensureDays($event, 1);

        Sanctum::actingAs($vendor);
        $first = $this->postJson('/api/bookings', $this->bookingPayload($event, [$sites[0]->id, $sites[1]->id]))
            ->assertCreated()
            ->assertJsonPath('invoice.amount', '40.00')
            ->json();
        $bookingId = (int) $first['booking']['id'];
        $this->createdBookingIds[] = $bookingId;
        $originalAmount = number_format((float) Invoice::where('booking_id', $bookingId)->value('amount'), 2, '.', '');

        Sanctum::actingAs($organizer);
        $this->putJson('/api/carboot-events/'.$event->id, [
            'title' => $event->title,
            'starts_at' => $event->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $event->ends_at->format('Y-m-d H:i:s'),
            'status' => $event->status,
            'site_price' => '25.00',
        ])->assertOk();

        $this->assertSame('40.00', $originalAmount);
        $this->assertSame(
            '40.00',
            number_format((float) Invoice::where('booking_id', $bookingId)->value('amount'), 2, '.', ''),
        );

        Sanctum::actingAs($vendor);
        $second = $this->postJson('/api/bookings', $this->bookingPayload($event, [$sites[2]->id]))
            ->assertCreated()
            ->assertJsonPath('invoice.amount', '25.00')
            ->json();
        $this->createdBookingIds[] = (int) $second['booking']['id'];
    }

    public function test_availability_api_returns_event_level_price_not_space_price(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createEvent($this->createOrganizer(), ['site_price' => '20.00']);
        $this->createSites($event, 2);
        $this->ensureDays($event, 1);

        Sanctum::actingAs($vendor);

        $this->getJson('/api/vendor/events/'.$event->id.'/site-availability?vendor_category_id='.$this->foodCategory()->id)
            ->assertOk()
            ->assertJsonPath('site_price', '20.00')
            ->assertJsonPath('sites.0.price', '20.00')
            ->assertJsonPath('sites.1.price', '20.00')
            ->assertJsonPath('sites.0.space_name', null);
    }

    public function test_reservation_service_ignores_client_price_fields_and_uses_event_site_price(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createEvent($this->createOrganizer(), ['site_price' => '20.00']);
        $sites = $this->createSites($event, 1);
        $this->ensureDays($event, 1);

        Sanctum::actingAs($vendor);
        $response = $this->postJson('/api/bookings', $this->bookingPayload($event, [$sites[0]->id], [
            'amount' => 50,
            'total' => 50,
            'invoice_amount' => 50,
            'unit_site_price' => 50,
            'site_quantity' => 9,
        ]))
            ->assertStatus(422);

        $ok = $this->postJson('/api/bookings', $this->bookingPayload($event, [$sites[0]->id]))
            ->assertCreated()
            ->assertJsonPath('invoice.amount', '20.00')
            ->json();

        $this->createdBookingIds[] = (int) $ok['booking']['id'];
        $booking = Booking::findOrFail((int) $ok['booking']['id']);
        $this->assertSame('20.00', number_format((float) $booking->unit_site_price, 2, '.', ''));
    }

    public function test_spaces_catalogue_api_does_not_expose_price(): void
    {
        Space::defaultPhysical();

        $this->getJson('/api/spaces')
            ->assertOk()
            ->assertJsonMissingPath('0.price');

        $payload = $this->getJson('/api/spaces')->json();
        $this->assertIsArray($payload);
        foreach ($payload as $row) {
            $this->assertArrayNotHasKey('price', $row);
            $this->assertSame(Space::PHYSICAL_PARKING_SITE, $row['space_size']);
        }
    }

    public function test_duplicate_site_ids_do_not_inflate_booking_quantity(): void
    {
        $vendor = $this->createVendor();
        $event = $this->createEvent($this->createOrganizer(), ['site_price' => '20.00']);
        $sites = $this->createSites($event, 2);
        $this->ensureDays($event, 1);
        Sanctum::actingAs($vendor);

        // Intentionally repeat the same site id — backend must count distinct sites only.
        $response = $this->postJson('/api/bookings', $this->bookingPayload($event, [
            $sites[0]->id,
            $sites[0]->id,
            $sites[0]->id,
        ]));

        // Either rejected as invalid selection or accepted as a single-site booking.
        if ($response->status() === 201) {
            $this->createdBookingIds[] = (int) $response->json('booking.id');
            $booking = Booking::findOrFail((int) $response->json('booking.id'));
            $this->assertSame(1, (int) $booking->site_quantity);
            $this->assertSame('20.00', number_format((float) $response->json('invoice.amount'), 2, '.', ''));
        } else {
            $response->assertStatus(422);
        }
    }
}
