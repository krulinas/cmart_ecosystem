<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorCategory;
use App\Services\BookingAllocationReservationService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\TestCase;

class VendorEventSiteAvailabilityTest extends TestCase
{
    use CleansUpTestFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function createUser(string $role = 'community', ?string $vendorStatus = null): User
    {
        $user = User::create([
            'name' => 'Avail Test ' . $role . ' ' . uniqid(),
            'email' => 'avail-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $vendorStatus ?? ($role === 'community' ? 'approved' : 'none'),
        ]);

        return $this->trackUser($user);
    }

    private function standardSpace(): Space
    {
        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 30.00, 'status' => 'Available'],
        );

        if ((float) $space->price !== 30.0) {
            $space->forceFill(['price' => 30.00, 'status' => 'Available'])->save();
        }

        return $space->fresh();
    }

    private function foodCategory(): VendorCategory
    {
        return VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
    }

    private function availabilityUrl(CarbootEvent $event, ?int $categoryId = null): string
    {
        $categoryId ??= $this->foodCategory()->id;

        return "/api/vendor/events/{$event->id}/site-availability?vendor_category_id={$categoryId}";
    }

    private function seedEventWithLayout(int $siteCount = 2): array
    {
        $starts = now()->addDays(20)->setTime(8, 0, 0);
        $event = CarbootEvent::create([
            'title' => 'Availability Test Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0, 0),
            'status' => 'Available',
            'description' => 'Phase 2A.8 availability test',
            'max_slots' => 50,
            'day_generation_mode' => 'calendar_days',
            'site_price' => CarbootEvent::DEFAULT_SITE_PRICE,
        ]);
        $this->trackEvent($event);

        $space = $this->standardSpace();
        $category = $this->foodCategory();
        $row = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'vendor_category_id' => $category->id,
            'label' => 'A',
            'slug' => 'a-' . $event->id,
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);

        $sites = [];

        for ($i = 1; $i <= $siteCount; $i++) {
            $site = EventSite::create([
                'carboot_event_id' => $event->id,
                'event_layout_row_id' => $row->id,
                'space_id' => $space->id,
                'label' => sprintf('A%02d', $i),
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

        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $starts->toDateString(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0, 0),
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
        $this->createdDayIds[] = $day->id;

        return [$event, $sites, $day, $space];
    }

    public function test_authenticated_vendor_receives_availability_payload(): void
    {
        $vendor = $this->createUser();
        [$event, $sites] = $this->seedEventWithLayout(2);

        Sanctum::actingAs($vendor);

        $response = $this->getJson($this->availabilityUrl($event))
            ->assertOk()
            ->assertJsonPath('event.id', $event->id)
            ->assertJsonPath('category_required', false)
            ->assertJsonPath('category.label', 'Food & Beverages')
            ->assertJsonPath('selection_rules.full_event_duration', true)
            ->assertJsonCount(2, 'sites')
            ->assertJsonPath('sites.0.label', 'A01')
            ->assertJsonPath('sites.0.availability_status', 'available')
            ->assertJsonPath('sites.0.is_selectable', true)
            ->assertJsonPath('sites.0.price', '20.00')
            ->assertJsonPath('site_price', '20.00')
            ->assertJsonPath('sites.0.space_name', null)
            ->assertJsonCount(1, 'operational_days');

        $json = $response->json();
        $this->assertArrayNotHasKey('active_lock', $json['sites'][0]);
        $this->assertArrayNotHasKey('booking_id', $json['sites'][0]);
    }

    public function test_availability_without_category_requires_selection(): void
    {
        $vendor = $this->createUser();
        [$event] = $this->seedEventWithLayout(2);

        Sanctum::actingAs($vendor);

        $this->getJson("/api/vendor/events/{$event->id}/site-availability")
            ->assertOk()
            ->assertJsonPath('category_required', true)
            ->assertJsonCount(0, 'sites')
            ->assertJsonPath('readiness.status', 'category_required');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        [$event] = $this->seedEventWithLayout(1);

        $this->getJson($this->availabilityUrl($event))
            ->assertUnauthorized();
    }

    public function test_cmart_management_receives_403(): void
    {
        $manager = $this->createUser('cmart_management');
        [$event] = $this->seedEventWithLayout(1);

        Sanctum::actingAs($manager);

        $this->getJson($this->availabilityUrl($event))
            ->assertForbidden();
    }

    /**
     * Canonical rule: booking operations gate on role:community membership only.
     * EnsureVendorApproved is intentionally dormant during onboarding
     * (see App\Http\Middleware\EnsureVendorApproved), and
     * CommunityVendorBookingAccessTest proves a vendor_status=none community user
     * may submit a booking. Availability must match that exact gate.
     */
    public function test_non_approved_community_user_can_load_availability(): void
    {
        $vendor = $this->createUser('community', 'none');
        [$event] = $this->seedEventWithLayout(2);

        Sanctum::actingAs($vendor);

        $this->getJson($this->availabilityUrl($event))
            ->assertOk()
            ->assertJsonCount(2, 'sites');
    }

    public function test_organizer_is_blocked_on_vendor_availability_route(): void
    {
        $organizer = $this->createUser('organizer');
        [$event] = $this->seedEventWithLayout(1);

        Sanctum::actingAs($organizer);

        $this->getJson($this->availabilityUrl($event))
            ->assertForbidden();
    }

    public function test_super_admin_is_blocked_on_vendor_only_availability_route(): void
    {
        $superAdmin = $this->createUser('super_admin');
        [$event] = $this->seedEventWithLayout(1);

        Sanctum::actingAs($superAdmin);

        $this->getJson($this->availabilityUrl($event))
            ->assertForbidden();
    }

    public function test_reserved_allocation_marks_site_occupied(): void
    {
        $vendor = $this->createUser();
        $other = $this->createUser();
        [$event, $sites, $day] = $this->seedEventWithLayout(1);

        $booking = Booking::create([
            'user_id' => $other->id,
            'space_id' => $sites[0]->space_id,
            'carboot_event_id' => $event->id,
            'booking_date' => $day->operational_date,
            'product_category' => 'Food & Beverages',
            'product_details' => 'Occupancy test',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;

        app(BookingAllocationReservationService::class)->reserveForBooking($booking, [$sites[0]->id]);
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $booking->id)->pluck('id')->all(),
        );

        Sanctum::actingAs($vendor);

        $this->getJson($this->availabilityUrl($event))
            ->assertOk()
            ->assertJsonPath('sites.0.availability_status', 'occupied')
            ->assertJsonPath('sites.0.occupancy_status', 'reserved')
            ->assertJsonPath('sites.0.is_selectable', false);
    }

    public function test_confirmed_occupancy_is_exposed_without_booking_identity(): void
    {
        $vendor = $this->createUser();
        $other = $this->createUser();
        [$event, $sites, $day] = $this->seedEventWithLayout(1);

        $booking = Booking::create([
            'user_id' => $other->id,
            'space_id' => $sites[0]->space_id,
            'carboot_event_id' => $event->id,
            'booking_date' => $day->operational_date,
            'product_category' => 'Food & Beverages',
            'product_details' => 'Confirmed occupancy test',
            'approval_status' => 'Approved',
        ]);
        $this->createdBookingIds[] = $booking->id;

        app(BookingAllocationReservationService::class)->reserveForBooking($booking, [$sites[0]->id]);
        BookingDayAllocation::where('booking_id', $booking->id)->update([
            'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $booking->id)->pluck('id')->all(),
        );

        Sanctum::actingAs($vendor);

        $site = $this->getJson($this->availabilityUrl($event))
            ->assertOk()
            ->assertJsonPath('sites.0.availability_status', 'occupied')
            ->assertJsonPath('sites.0.occupancy_status', 'confirmed')
            ->json('sites.0');

        $this->assertArrayNotHasKey('booking_id', $site);
        $this->assertArrayNotHasKey('allocation_id', $site);
        $this->assertArrayNotHasKey('vendor', $site);
    }

    public function test_released_allocation_does_not_block_availability(): void
    {
        $vendor = $this->createUser();
        [$event, $sites, $day] = $this->seedEventWithLayout(1);

        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $sites[0]->space_id,
            'carboot_event_id' => $event->id,
            'booking_date' => $day->operational_date,
            'product_category' => 'Food & Beverages',
            'product_details' => 'Released history',
            'approval_status' => 'Rejected',
        ]);
        $this->createdBookingIds[] = $booking->id;

        $allocation = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $sites[0]->id,
            'allocation_status' => BookingDayAllocation::STATUS_RELEASED,
            'reserved_at' => now()->subDay(),
            'released_at' => now(),
            'active_lock' => null,
        ]);
        $this->createdAllocationIds[] = $allocation->id;

        Sanctum::actingAs($vendor);

        $this->getJson($this->availabilityUrl($event))
            ->assertOk()
            ->assertJsonPath('sites.0.availability_status', 'available');
    }

    public function test_disabled_site_is_not_selectable(): void
    {
        $vendor = $this->createUser();
        [$event, $sites] = $this->seedEventWithLayout(1);
        $sites[0]->update(['operational_status' => EventSite::STATUS_DISABLED]);

        Sanctum::actingAs($vendor);

        $this->getJson($this->availabilityUrl($event))
            ->assertOk()
            ->assertJsonPath('sites.0.availability_status', 'disabled')
            ->assertJsonPath('sites.0.is_selectable', false);
    }

    public function test_event_without_active_days_returns_422(): void
    {
        $vendor = $this->createUser();
        $starts = now()->addDays(25)->setTime(8, 0, 0);
        $event = CarbootEvent::create([
            'title' => 'No Days Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0, 0),
            'status' => 'Available',
            'description' => 'No days',
            'max_slots' => 50,
            'day_generation_mode' => 'calendar_days',
        ]);
        $this->trackEvent($event);

        Sanctum::actingAs($vendor);

        $this->getJson($this->availabilityUrl($event))
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'no_active_event_days']);
    }

    public function test_event_with_no_sites_returns_readiness_message(): void
    {
        $vendor = $this->createUser();
        $starts = now()->addDays(22)->setTime(8, 0, 0);
        $event = CarbootEvent::create([
            'title' => 'Empty Layout Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0, 0),
            'status' => 'Available',
            'description' => 'No sites',
            'max_slots' => 50,
            'day_generation_mode' => 'calendar_days',
        ]);
        $this->trackEvent($event);

        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $starts->toDateString(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0, 0),
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
        $this->createdDayIds[] = $day->id;

        Sanctum::actingAs($vendor);

        $this->getJson($this->availabilityUrl($event))
            ->assertOk()
            ->assertJsonCount(0, 'sites')
            ->assertJsonPath('readiness.status', 'no_compatible_sites');
    }

    public function test_cancelled_event_day_is_excluded_from_operational_days(): void
    {
        $vendor = $this->createUser();
        [$event] = $this->seedEventWithLayout(1);

        $cancelled = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $event->starts_at->copy()->addDay()->toDateString(),
            'starts_at' => $event->starts_at->copy()->addDay(),
            'ends_at' => $event->starts_at->copy()->addDay()->setTime(17, 0, 0),
            'operational_status' => EventDay::STATUS_CANCELLED,
            'display_order' => 2,
        ]);
        $this->createdDayIds[] = $cancelled->id;

        Sanctum::actingAs($vendor);

        $this->getJson($this->availabilityUrl($event))
            ->assertOk()
            ->assertJsonCount(1, 'operational_days');
    }
}
