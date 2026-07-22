<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorCategory;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\TestCase;

/**
 * Phase 2A.7 — vendor booking creation with physical site allocations.
 */
class BookingCreationWithAllocationsTest extends TestCase
{
    use CleansUpTestFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function createUser(string $role = 'community'): User
    {
        $user = User::create([
            'name' => 'Create Alloc ' . $role . ' ' . uniqid(),
            'email' => 'create-alloc-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]);

        return $this->trackUser($user);
    }

    private function standardSpace(): Space
    {
        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 30.00, 'status' => 'Available'],
        );

        // Shared catalogue row: earlier tests may have firstOrCreate'd at another price.
        if ((float) $space->price !== 30.0) {
            $space->forceFill(['price' => 30.00, 'status' => 'Available'])->save();
        }

        return $space->fresh();
    }

    private function largeSpace(): Space
    {
        return Space::query()->firstOrCreate(
            ['space_size' => 'Large (2 Parking Lots)'],
            ['price' => 50.00, 'status' => 'Available'],
        );
    }

    private function createEvent(int $dayCount = 1): CarbootEvent
    {
        $starts = now()->addDays(12)->setTime(8, 0, 0);
        $ends = $starts->copy()->addDays(max(0, $dayCount - 1))->setTime(17, 0, 0);

        $event = CarbootEvent::create([
            'title' => 'Creation Test Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => 'Available',
            'description' => 'Phase 2A.7 creation test',
            'max_slots' => 50,
            'day_generation_mode' => 'calendar_days',
            'site_price' => CarbootEvent::DEFAULT_SITE_PRICE,
        ]);

        return $this->trackEvent($event);
    }

    private function foodCategory(): VendorCategory
    {
        return VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
    }

    private function ensureLayoutRow(CarbootEvent $event, string $rowLabel, ?VendorCategory $category = null): EventLayoutRow
    {
        $category ??= $this->foodCategory();

        return EventLayoutRow::query()->firstOrCreate(
            [
                'carboot_event_id' => $event->id,
                'label' => $rowLabel,
            ],
            [
                'vendor_category_id' => $category->id,
                'slug' => strtolower($rowLabel) . '-row-' . $event->id,
                'display_order' => max(1, ord(strtoupper($rowLabel[0] ?? 'A')) - 64),
                'is_active' => true,
                'is_public' => true,
            ],
        );
    }

    private function createSite(
        CarbootEvent $event,
        Space $space,
        string $label,
        string $row,
        int $position,
        ?VendorCategory $category = null,
    ): EventSite {
        $layoutRow = $this->ensureLayoutRow($event, $row, $category);

        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'event_layout_row_id' => $layoutRow->id,
            'space_id' => $space->id,
            'label' => $label,
            'row_label' => $row,
            'position_number' => $position,
            'grid_row' => 1,
            'grid_column' => $position,
            'display_order' => $position,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->createdSiteIds[] = $site->id;

        return $site;
    }

    private function createDays(CarbootEvent $event, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $date = $event->starts_at->copy()->addDays($i)->toDateString();
            $day = EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $date,
                'starts_at' => $date . ' 08:00:00',
                'ends_at' => $date . ' 17:00:00',
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => $i + 1,
            ]);
            $this->createdDayIds[] = $day->id;
        }
    }

    private function bookingPayload(CarbootEvent $event, array $siteIds, array $overrides = []): array
    {
        return array_merge([
            'event_id' => $event->id,
            'event_site_ids' => $siteIds,
            'vendor_category_id' => $this->foodCategory()->id,
            'product_category' => 'Food & Beverages',
            'product_details' => 'Phase 2A.7 booking creation test',
        ], $overrides);
    }

    private function trackCreatedBookingResponse(array $response): int
    {
        $bookingId = (int) $response['booking']['id'];
        $this->createdBookingIds[] = $bookingId;
        $this->createdInvoiceIds[] = (int) $response['invoice']['id'];
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $bookingId)->pluck('id')->all(),
        );

        return $bookingId;
    }

    public function test_one_site_one_day_creates_booking_invoice_and_allocations(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A05', 'A', 5);
        $this->createDays($event, 1);

        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/bookings', $this->bookingPayload($event, [$site->id]))
            ->assertCreated()
            ->assertJsonPath('booking.approval_status', 'Pending_Organizer')
            ->assertJsonPath('booking.site_selection.site_count', 1)
            ->assertJsonPath('booking.site_selection.active_day_count', 1)
            ->assertJsonPath('booking.site_selection.allocation_count', 1)
            ->assertJsonPath('booking.site_selection.allocation_status', 'reserved')
            ->assertJsonPath('booking.site_selection.sites.0.label', 'A05')
            ->assertJsonPath('booking.tapak_quantity', 1)
            ->assertJsonPath('booking.vendor_category_id', $this->foodCategory()->id)
            ->assertJsonPath('booking.category_label_snapshot', 'Food & Beverages')
            ->assertJsonPath('booking.product_category', 'Food & Beverages')
            ->assertJsonPath('invoice.amount', '20.00')
            ->json();

        $bookingId = $this->trackCreatedBookingResponse($response);
        $booking = Booking::findOrFail($bookingId);

        $this->assertSame($space->id, $booking->space_id);
        $this->assertSame('20.00', number_format((float) $booking->unit_site_price, 2, '.', ''));
        $this->assertSame(1, (int) $booking->site_quantity);
        $this->assertSame(1, BookingDayAllocation::where('booking_id', $bookingId)->count());
        $this->assertDatabaseHas('booking_day_allocations', [
            'booking_id' => $bookingId,
            'event_site_id' => $site->id,
            'allocation_status' => 'reserved',
            'active_lock' => 1,
        ]);
    }

    public function test_two_adjacent_sites_two_days_creates_four_allocations(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(2);
        $space = $this->standardSpace();
        $siteA = $this->createSite($event, $space, 'B01', 'B', 1);
        $siteB = $this->createSite($event, $space, 'B02', 'B', 2);
        $this->createDays($event, 2);

        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/bookings', $this->bookingPayload($event, [$siteA->id, $siteB->id]))
            ->assertCreated()
            ->assertJsonPath('booking.site_selection.site_count', 2)
            ->assertJsonPath('booking.site_selection.active_day_count', 2)
            ->assertJsonPath('booking.site_selection.allocation_count', 4)
            ->assertJsonPath('invoice.amount', '40.00')
            ->json();

        $this->trackCreatedBookingResponse($response);
    }

    public function test_three_adjacent_sites_four_days_creates_twelve_allocations(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(4);
        $space = $this->standardSpace();
        $sites = [
            $this->createSite($event, $space, 'C01', 'C', 1),
            $this->createSite($event, $space, 'C02', 'C', 2),
            $this->createSite($event, $space, 'C03', 'C', 3),
        ];
        $this->createDays($event, 4);

        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/bookings', $this->bookingPayload($event, collect($sites)->pluck('id')->all()))
            ->assertCreated()
            ->assertJsonPath('booking.site_selection.allocation_count', 12)
            ->assertJsonPath('invoice.amount', '60.00')
            ->json();

        $this->trackCreatedBookingResponse($response);
    }

    public function test_client_quantity_space_and_amount_cannot_override_backend_derivation(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $large = $this->largeSpace();
        $site = $this->createSite($event, $space, 'D01', 'D', 1);
        $this->createDays($event, 1);

        Sanctum::actingAs($vendor);

        $this->postJson('/api/bookings', $this->bookingPayload($event, [$site->id], [
            'tapak_quantity' => 99,
            'total_price' => 999,
            'space_id' => $large->id,
            'amount' => 999,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $response = $this->postJson('/api/bookings', $this->bookingPayload($event, [$site->id], [
            'tapak_quantity' => 99,
            'total_price' => 999,
            'space_id' => $large->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('booking.tapak_quantity', 1)
            ->assertJsonPath('invoice.amount', '20.00')
            ->json();

        $bookingId = $this->trackCreatedBookingResponse($response);
        $this->assertSame($space->id, Booking::findOrFail($bookingId)->space_id);
    }

    public function test_missing_event_site_ids_returns_422(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $this->createDays($event, 1);

        Sanctum::actingAs($vendor);

        $bookingsBefore = Booking::count();
        $invoicesBefore = Invoice::count();
        $allocationsBefore = BookingDayAllocation::count();

        $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'product_category' => 'Food & Beverages',
            'product_details' => 'Missing sites',
        ])->assertStatus(422);

        $this->assertSame($bookingsBefore, Booking::count());
        $this->assertSame($invoicesBefore, Invoice::count());
        $this->assertSame($allocationsBefore, BookingDayAllocation::count());
    }

    public function test_duplicate_event_site_ids_return_422_without_persisting_booking(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'E01', 'E', 1);
        $this->createDays($event, 1);

        Sanctum::actingAs($vendor);

        $bookingsBefore = Booking::count();

        $this->postJson('/api/bookings', $this->bookingPayload($event, [$site->id, $site->id]))
            ->assertStatus(422);

        $this->assertSame($bookingsBefore, Booking::count());
    }

    public function test_event_without_active_days_returns_clear_422(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'F01', 'F', 1);

        Sanctum::actingAs($vendor);

        $bookingsBefore = Booking::count();

        $this->postJson('/api/bookings', $this->bookingPayload($event, [$site->id]))
            ->assertStatus(409)
            ->assertJsonFragment([
                'error' => 'EVENT_LAYOUT_NOT_READY',
            ]);

        $this->assertSame($bookingsBefore, Booking::count());
    }

    public function test_non_adjacent_sites_return_422_with_full_rollback(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $siteA = $this->createSite($event, $space, 'G01', 'G', 1);
        $siteC = $this->createSite($event, $space, 'G03', 'G', 3);
        $this->createDays($event, 1);

        Sanctum::actingAs($vendor);

        $bookingsBefore = Booking::count();
        $auditBefore = BookingAuditLog::count();

        $this->postJson('/api/bookings', $this->bookingPayload($event, [$siteA->id, $siteC->id]))
            ->assertStatus(422);

        $this->assertSame($bookingsBefore, Booking::count());
        $this->assertSame($auditBefore, BookingAuditLog::count());
    }

    public function test_occupied_site_returns_409_with_full_rollback(): void
    {
        $vendorA = $this->createUser();
        $vendorB = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'H01', 'H', 1);
        $this->createDays($event, 1);

        Sanctum::actingAs($vendorA);
        $first = $this->postJson('/api/bookings', $this->bookingPayload($event, [$site->id]))
            ->assertCreated()
            ->json();
        $this->trackCreatedBookingResponse($first);

        $bookingsBeforeSecond = Booking::count();
        $invoicesBeforeSecond = Invoice::count();

        Sanctum::actingAs($vendorB);
        $this->postJson('/api/bookings', $this->bookingPayload($event, [$site->id]))
            ->assertStatus(409)
            ->assertJsonFragment(['error' => 'site_day_occupied']);

        $this->assertSame($bookingsBeforeSecond, Booking::count());
        $this->assertSame($invoicesBeforeSecond, Invoice::count());
    }

    public function test_unauthenticated_booking_submission_returns_401(): void
    {
        $event = $this->createEvent(1);

        $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [1],
            'product_category' => 'Food & Beverages',
            'product_details' => 'Auth test',
        ])->assertUnauthorized();
    }

    public function test_cmart_management_cannot_submit_booking(): void
    {
        $manager = $this->createUser('cmart_management');
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'I01', 'I', 1);
        $this->createDays($event, 1);

        Sanctum::actingAs($manager);

        $this->postJson('/api/bookings', $this->bookingPayload($event, [$site->id]))
            ->assertForbidden();
    }
}
