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
use App\Models\VendorBusinessProfile;
use App\Models\VendorCategory;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\EnsuresCanonicalLayoutForSites;
use Tests\TestCase;

class Phase37VendorCategoryEligibilityTest extends TestCase
{
    use CleansUpTestFixtures;
    use EnsuresCanonicalLayoutForSites;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function vendor(): User
    {
        return $this->trackUser(User::create([
            'name' => 'P37 Vendor ' . uniqid(),
            'email' => 'p37-vendor-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'approved',
        ]));
    }

    private function space(): Space
    {
        return Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 30.00, 'status' => 'Available'],
        );
    }

    private function seedDualCategoryEvent(): array
    {
        $starts = now()->addDays(18)->setTime(8, 0);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'P37 Dual Cat ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0),
            'status' => 'Available',
            'description' => 'Phase 3.7 eligibility',
            'max_slots' => 40,
            'day_generation_mode' => 'calendar_days',
        ]));

        $food = $this->foodVendorCategory();
        $thrift = VendorCategory::query()->where('slug', 'pre-loved-thrift')->firstOrFail();
        $space = $this->space();

        $rowFood = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'vendor_category_id' => $food->id,
            'label' => 'B',
            'slug' => 'b-' . $event->id,
            'display_order' => 2,
            'is_active' => true,
            'is_public' => true,
        ]);
        $rowThrift = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'vendor_category_id' => $thrift->id,
            'label' => 'A',
            'slug' => 'a-' . $event->id,
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);

        $sites = [];
        foreach ([[$rowThrift, 'A01', 'A', 1], [$rowThrift, 'A02', 'A', 2], [$rowFood, 'B01', 'B', 1], [$rowFood, 'B02', 'B', 2]] as [$row, $label, $rowLabel, $pos]) {
            $site = EventSite::create([
                'carboot_event_id' => $event->id,
                'event_layout_row_id' => $row->id,
                'space_id' => $space->id,
                'label' => $label,
                'row_label' => $rowLabel,
                'position_number' => $pos,
                'grid_row' => 1,
                'grid_column' => $pos,
                'display_order' => $pos,
                'operational_status' => EventSite::STATUS_ACTIVE,
            ]);
            $this->createdSiteIds[] = $site->id;
            $sites[$label] = $site;
        }

        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $starts->toDateString(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0),
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
        $this->createdDayIds[] = $day->id;

        return [$event, $sites, $food, $thrift];
    }

    public function test_vendor_categories_endpoint_is_public_and_safe(): void
    {
        $response = $this->getJson('/api/vendor-categories')
            ->assertOk()
            ->assertJsonCount(7, 'categories');

        $first = $response->json('categories.0');
        $this->assertSame('Pre-loved / Thrift', $first['label']);
        $this->assertArrayNotHasKey('usage', $first);
        $this->assertArrayNotHasKey('archived_at', $first);
    }

    public function test_availability_filters_by_category(): void
    {
        $vendor = $this->vendor();
        [$event, $sites, $food, $thrift] = $this->seedDualCategoryEvent();
        Sanctum::actingAs($vendor);

        $foodPayload = $this->getJson("/api/vendor/events/{$event->id}/site-availability?vendor_category_id={$food->id}")
            ->assertOk()
            ->json();
        $this->assertSame(['B01', 'B02'], collect($foodPayload['sites'])->pluck('label')->sort()->values()->all());
        $this->assertCount(1, $foodPayload['rows']);
        $this->assertSame($food->id, $foodPayload['rows'][0]['category']['id']);
        $this->assertSame('B', $foodPayload['rows'][0]['label']);
        $this->assertArrayHasKey('description', $foodPayload['rows'][0]);
        $this->assertArrayHasKey('display_order', $foodPayload['rows'][0]);
        $this->assertArrayNotHasKey('locks', $foodPayload['rows'][0]);
        $this->assertArrayNotHasKey('override', $foodPayload['rows'][0]);

        $thriftPayload = $this->getJson("/api/vendor/events/{$event->id}/site-availability?vendor_category_id={$thrift->id}")
            ->assertOk()
            ->json();
        $this->assertSame(['A01', 'A02'], collect($thriftPayload['sites'])->pluck('label')->sort()->values()->all());
    }

    public function test_compatible_booking_writes_canonical_fk_and_snapshot(): void
    {
        $vendor = $this->vendor();
        [$event, $sites, $food] = $this->seedDualCategoryEvent();
        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$sites['B01']->id, $sites['B02']->id],
            'vendor_category_id' => $food->id,
            'product_details' => 'Drinks and snacks for Phase 3.7',
        ])->assertCreated()->json();

        $bookingId = (int) $response['booking']['id'];
        $this->createdBookingIds[] = $bookingId;
        $this->createdInvoiceIds[] = (int) $response['invoice']['id'];
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $bookingId)->pluck('id')->all(),
        );

        $booking = Booking::findOrFail($bookingId);
        $this->assertSame($food->id, $booking->vendor_category_id);
        $this->assertSame('Food & Beverages', $booking->category_label_snapshot);
        $this->assertSame('Food & Beverages', $booking->product_category);
        $this->assertSame('Food & Beverages', $response['booking']['category']['label']);
    }

    public function test_incompatible_site_rejected_without_residue(): void
    {
        $vendor = $this->vendor();
        [$event, $sites, $food] = $this->seedDualCategoryEvent();
        Sanctum::actingAs($vendor);

        $bookingsBefore = Booking::count();
        $invoicesBefore = Invoice::count();
        $allocationsBefore = BookingDayAllocation::count();

        $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$sites['A01']->id],
            'vendor_category_id' => $food->id,
            'product_details' => 'Forced incompatible thrift site',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'SITE_CATEGORY_INCOMPATIBLE']);

        $this->assertSame($bookingsBefore, Booking::count());
        $this->assertSame($invoicesBefore, Invoice::count());
        $this->assertSame($allocationsBefore, BookingDayAllocation::count());
    }

    public function test_mixed_category_sites_rejected_atomically(): void
    {
        $vendor = $this->vendor();
        [$event, $sites, $food] = $this->seedDualCategoryEvent();
        Sanctum::actingAs($vendor);

        $bookingsBefore = Booking::count();

        $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$sites['A01']->id, $sites['B01']->id],
            'vendor_category_id' => $food->id,
            'product_details' => 'Mixed rows',
        ])->assertStatus(422);

        $this->assertSame($bookingsBefore, Booking::count());
    }

    public function test_others_alias_writes_mixed_others_mirror(): void
    {
        $vendor = $this->vendor();
        $starts = now()->addDays(19)->setTime(8, 0);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'P37 Others ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0),
            'status' => 'Available',
            'description' => 'Others alias',
            'max_slots' => 20,
            'day_generation_mode' => 'calendar_days',
        ]));
        $mixed = VendorCategory::query()->where('slug', 'mixed-others')->firstOrFail();
        $row = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'vendor_category_id' => $mixed->id,
            'label' => 'M',
            'slug' => 'm-' . $event->id,
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);
        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'event_layout_row_id' => $row->id,
            'space_id' => $this->space()->id,
            'label' => 'M01',
            'row_label' => 'M',
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->createdSiteIds[] = $site->id;
        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $starts->toDateString(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0),
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
        $this->createdDayIds[] = $day->id;

        Sanctum::actingAs($vendor);
        $response = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$site->id],
            'product_category' => 'Others',
            'product_details' => 'Legacy Others alias payload',
        ])->assertCreated()->json();

        $bookingId = (int) $response['booking']['id'];
        $this->createdBookingIds[] = $bookingId;
        $this->createdInvoiceIds[] = (int) $response['invoice']['id'];
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $bookingId)->pluck('id')->all(),
        );

        $booking = Booking::findOrFail($bookingId);
        $this->assertSame($mixed->id, $booking->vendor_category_id);
        $this->assertSame('Mixed / Others', $booking->product_category);
        $this->assertSame('Mixed / Others', $booking->category_label_snapshot);
    }

    public function test_stale_row_category_change_rejected(): void
    {
        $vendor = $this->vendor();
        [$event, $sites, $food, $thrift] = $this->seedDualCategoryEvent();
        Sanctum::actingAs($vendor);

        $bookingsBefore = Booking::count();

        EventLayoutRow::where('carboot_event_id', $event->id)
            ->where('label', 'B')
            ->update(['vendor_category_id' => $thrift->id]);

        $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$sites['B01']->id, $sites['B02']->id],
            'vendor_category_id' => $food->id,
            'product_details' => 'Stale food selection',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'SITE_CATEGORY_INCOMPATIBLE']);

        $this->assertSame($bookingsBefore, Booking::count());
    }

    public function test_profile_category_writes_fk_and_does_not_restrict_booking(): void
    {
        $vendor = $this->vendor();
        $food = $this->foodVendorCategory();
        $thrift = VendorCategory::query()->where('slug', 'pre-loved-thrift')->firstOrFail();

        Sanctum::actingAs($vendor);
        $this->putJson('/api/vendor/business-profile', [
            'business_name' => $vendor->name,
            'vendor_category_id' => $thrift->id,
        ])->assertOk()
            ->assertJsonPath('profile.vendor_category_id', $thrift->id)
            ->assertJsonPath('profile.business_category', 'Pre-loved / Thrift');

        [$event, $sites, $foodCat] = $this->seedDualCategoryEvent();
        $response = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$sites['B01']->id],
            'vendor_category_id' => $foodCat->id,
            'product_details' => 'Different from profile',
        ])->assertCreated()->json();

        $bookingId = (int) $response['booking']['id'];
        $this->createdBookingIds[] = $bookingId;
        $this->createdInvoiceIds[] = (int) $response['invoice']['id'];
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $bookingId)->pluck('id')->all(),
        );

        $this->assertSame($food->id, Booking::find($bookingId)->vendor_category_id);
        $this->assertSame($thrift->id, VendorBusinessProfile::where('user_id', $vendor->id)->value('vendor_category_id'));
    }

    public function test_cross_user_booking_context_forbidden_on_availability(): void
    {
        $owner = $this->vendor();
        $intruder = $this->vendor();
        [$event, $sites, $food] = $this->seedDualCategoryEvent();

        Sanctum::actingAs($owner);
        $created = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$sites['B01']->id],
            'vendor_category_id' => $food->id,
            'product_details' => 'Owner booking',
        ])->assertCreated()->json();
        $bookingId = (int) $created['booking']['id'];
        $this->createdBookingIds[] = $bookingId;
        $this->createdInvoiceIds[] = (int) $created['invoice']['id'];
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $bookingId)->pluck('id')->all(),
        );

        Sanctum::actingAs($intruder);
        $this->getJson("/api/vendor/events/{$event->id}/site-availability?booking_id={$bookingId}")
            ->assertForbidden()
            ->assertJsonFragment(['error' => 'BOOKING_CONTEXT_FORBIDDEN']);
    }

    public function test_category_change_blocked_when_retained_sites_incompatible(): void
    {
        $vendor = $this->vendor();
        [$event, $sites, $food, $thrift] = $this->seedDualCategoryEvent();
        Sanctum::actingAs($vendor);

        $created = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$sites['B01']->id, $sites['B02']->id],
            'vendor_category_id' => $food->id,
            'product_details' => 'Food booking for revision',
        ])->assertCreated()->json();
        $bookingId = (int) $created['booking']['id'];
        $this->createdBookingIds[] = $bookingId;
        $this->createdInvoiceIds[] = (int) $created['invoice']['id'];
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $bookingId)->pluck('id')->all(),
        );

        $this->patchJson("/api/vendor/bookings/{$bookingId}", [
            'vendor_category_id' => $thrift->id,
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'SITE_CATEGORY_INCOMPATIBLE']);

        $booking = Booking::findOrFail($bookingId);
        $this->assertSame($food->id, $booking->vendor_category_id);
        $this->assertSame('Food & Beverages', $booking->category_label_snapshot);
    }
}
