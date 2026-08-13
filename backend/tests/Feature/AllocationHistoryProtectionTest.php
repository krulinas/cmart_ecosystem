<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Services\BookingAllocationReservationService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2A.6 — allocation history protects sites, days, generators, and booking delete.
 */
class AllocationHistoryProtectionTest extends TestCase
{
    private array $createdUserIds = [];
    private array $createdEventIds = [];
    private array $createdSiteIds = [];
    private array $createdDayIds = [];
    private array $createdBookingIds = [];
    private array $createdAllocationIds = [];
    private array $createdInvoiceIds = [];

    protected function tearDown(): void
    {
        if ($this->createdAllocationIds !== []) {
            BookingDayAllocation::whereIn('id', $this->createdAllocationIds)->delete();
            $this->createdAllocationIds = [];
        }

        if ($this->createdInvoiceIds !== []) {
            Invoice::whereIn('id', $this->createdInvoiceIds)->delete();
            $this->createdInvoiceIds = [];
        }

        if ($this->createdBookingIds !== []) {
            Booking::whereIn('id', $this->createdBookingIds)->delete();
            $this->createdBookingIds = [];
        }

        if ($this->createdSiteIds !== []) {
            EventSite::whereIn('id', $this->createdSiteIds)->delete();
            $this->createdSiteIds = [];
        }

        if ($this->createdDayIds !== []) {
            EventDay::whereIn('id', $this->createdDayIds)->delete();
            $this->createdDayIds = [];
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
            'name' => 'HistProt ' . $role . ' ' . uniqid(),
            'email' => 'histprot-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ], $overrides));
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createEvent(): CarbootEvent
    {
        $event = CarbootEvent::query()->create([
            'title' => 'History Protection ' . uniqid(),
            'starts_at' => now()->addDays(14)->setTime(8, 0),
            'ends_at' => now()->addDays(15)->setTime(17, 0),
            'status' => 'Available',
            'description' => 'Phase 2A.6 history protection',
            'max_slots' => 40,
            'day_generation_mode' => 'calendar_days',
        ]);
        $this->createdEventIds[] = $event->id;

        return $event;
    }

    private function standardSpace(): Space
    {
        return Space::defaultPhysical();
    }

    private function createSite(CarbootEvent $event, Space $space, array $overrides = []): EventSite
    {
        $site = EventSite::create(array_merge([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => 'A01',
            'row_label' => 'A',
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ], $overrides));
        $this->createdSiteIds[] = $site->id;

        return $site;
    }

    private function createDay(CarbootEvent $event, string $date, int $order = 1): EventDay
    {
        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $date,
            'starts_at' => $date . ' 08:00:00',
            'ends_at' => $date . ' 17:00:00',
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => $order,
        ]);
        $this->createdDayIds[] = $day->id;

        return $day;
    }

    private function createBookingWithAllocation(CarbootEvent $event, EventSite $site, EventDay $day): Booking
    {
        $vendor = $this->createUser('community');
        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $site->space_id,
            'carboot_event_id' => $event->id,
            'booking_date' => $day->operational_date->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'history protection fixture',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 20,
            'payment_status' => 'Unpaid',
        ]);
        $this->createdInvoiceIds[] = $invoice->id;

        $allocation = BookingDayAllocation::factory()->reserved()->create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
        ]);
        $this->createdAllocationIds[] = $allocation->id;

        return $booking;
    }

    public function test_event_day_with_history_cannot_be_deleted_or_rewritten(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space);
        $date = $event->starts_at->toDateString();
        $day = $this->createDay($event, $date);
        $this->createBookingWithAllocation($event, $site, $day);

        Sanctum::actingAs($organizer);

        $this->deleteJson("/api/organizer/event-days/{$day->id}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'event_day_has_allocation_history');

        $this->patchJson("/api/organizer/event-days/{$day->id}", [
            'operational_date' => $event->starts_at->copy()->addDay()->toDateString(),
            'starts_at' => $date . ' 08:00:00',
            'ends_at' => $date . ' 17:00:00',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'event_day_history_structural_lock');

        $this->patchJson("/api/organizer/event-days/{$day->id}", [
            'operational_status' => EventDay::STATUS_DISABLED,
        ])->assertOk()
            ->assertJsonPath('day.operational_status', 'disabled');
    }

    public function test_day_replace_existing_blocked_when_history_exists(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, ['label' => 'A01']);
        $day = $this->createDay($event, $event->starts_at->toDateString());
        $this->createBookingWithAllocation($event, $site, $day);

        $siteCountBefore = EventSite::query()->forEvent($event->id)->count();
        $dayCountBefore = EventDay::query()->forEvent($event->id)->count();

        Sanctum::actingAs($organizer);

        $this->postJson("/api/organizer/events/{$event->id}/days/generate", [
            'replace_existing' => true,
        ])->assertStatus(409)
            ->assertJsonPath('error', 'event_day_replace_blocked_by_history');

        $this->assertSame($siteCountBefore, EventSite::query()->forEvent($event->id)->count());
        $this->assertSame($dayCountBefore, EventDay::query()->forEvent($event->id)->count());
        $this->assertDatabaseHas('event_sites', ['id' => $site->id, 'label' => 'A01']);
        $this->assertDatabaseHas('event_days', ['id' => $day->id]);
    }

    public function test_booking_with_allocation_history_cannot_be_hard_deleted(): void
    {
        $organizer = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space);
        $day = $this->createDay($event, $event->starts_at->toDateString());
        $booking = $this->createBookingWithAllocation($event, $site, $day);

        Sanctum::actingAs($organizer);
        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'booking_has_allocation_history');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
        $this->assertDatabaseHas('booking_day_allocations', ['booking_id' => $booking->id]);
    }

    public function test_booking_without_allocations_may_still_be_deleted(): void
    {
        $organizer = $this->createUser('organizer');
        $vendor = $this->createUser('community');
        $event = $this->createEvent();
        $space = $this->standardSpace();

        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'deletable',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 20,
            'payment_status' => 'Unpaid',
        ]);
        $this->createdInvoiceIds[] = $invoice->id;

        Sanctum::actingAs($organizer);
        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertOk();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->createdBookingIds = array_values(array_filter(
            $this->createdBookingIds,
            fn ($id) => $id !== $booking->id
        ));
        $this->createdInvoiceIds = array_values(array_filter(
            $this->createdInvoiceIds,
            fn ($id) => $id !== $invoice->id
        ));
    }

    public function test_governance_boundaries_for_site_and_day_management_remain(): void
    {
        $event = $this->createEvent();
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space);
        $day = $this->createDay($event, $event->starts_at->toDateString());

        $this->getJson("/api/organizer/events/{$event->id}/layout")->assertUnauthorized();
        $this->getJson("/api/organizer/events/{$event->id}/days")->assertUnauthorized();

        Sanctum::actingAs($this->createUser('community'));
        $this->getJson("/api/organizer/events/{$event->id}/layout")->assertForbidden();
        $this->getJson("/api/organizer/events/{$event->id}/days")->assertForbidden();

        Sanctum::actingAs($this->createUser('cmart_management'));
        $this->getJson("/api/organizer/events/{$event->id}/layout")->assertForbidden();
        $this->deleteJson("/api/organizer/events/{$event->id}/layout/sites/{$site->id}")->assertForbidden();
        $this->deleteJson("/api/organizer/event-days/{$day->id}")->assertForbidden();

        Sanctum::actingAs($this->createUser('organizer'));
        $this->getJson("/api/organizer/events/{$event->id}/layout")->assertOk();
        $this->getJson("/api/organizer/events/{$event->id}/days")->assertOk();

        Sanctum::actingAs($this->createUser('super_admin'));
        $this->getJson("/api/organizer/events/{$event->id}/layout")->assertOk();
        $this->getJson("/api/organizer/events/{$event->id}/days")->assertOk();
    }

    public function test_reservation_service_still_usable_and_no_public_allocation_routes(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri());
        $this->assertFalse($routes->contains(fn ($uri) => str_contains($uri, 'allocations')));
        $this->assertFalse($routes->contains(fn ($uri) => str_contains($uri, 'reserve-sites')));

        $event = $this->createEvent();
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, ['label' => 'A02', 'position_number' => 2, 'grid_column' => 2]);
        $this->createDay($event, $event->starts_at->toDateString());
        $vendor = $this->createUser('community');
        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'service-only',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 20,
            'payment_status' => 'Unpaid',
        ]);
        $this->createdInvoiceIds[] = $invoice->id;

        $result = app(BookingAllocationReservationService::class)
            ->reserveForBooking($booking, [$site->id]);
        foreach ($result->allocations as $allocation) {
            $this->createdAllocationIds[] = $allocation->id;
        }
        $this->assertCount(1, $result->allocations);
    }
}
