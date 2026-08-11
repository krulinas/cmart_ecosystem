<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingCategoryOverride;
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
use Tests\Concerns\EnsuresCanonicalLayoutForSites;
use Tests\TestCase;

class OrganizerBookingSiteReassignmentTest extends TestCase
{
    use CleansUpTestFixtures;
    use EnsuresCanonicalLayoutForSites;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return $this->trackUser(User::create([
            'name' => 'P38 ' . ucfirst($role) . ' ' . uniqid(),
            'email' => 'p38-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]));
    }

    private function space(): Space
    {
        return Space::defaultPhysical();
    }

    /**
     * @return array{0: CarbootEvent, 1: array<string, EventSite>, 2: VendorCategory, 3: VendorCategory, 4: EventDay}
     */
    private function seedDualCategoryEvent(): array
    {
        $starts = now()->addDays(20)->setTime(8, 0);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'P38 Reassign ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0),
            'status' => 'Available',
            'description' => 'Phase 3.8 reassignment',
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
        foreach ([[$rowThrift, 'A01', 'A', 1], [$rowThrift, 'A02', 'A', 2], [$rowFood, 'B01', 'B', 1], [$rowFood, 'B02', 'B', 2], [$rowFood, 'B03', 'B', 3], [$rowFood, 'B04', 'B', 4]] as [$row, $label, $rowLabel, $pos]) {
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

        return [$event, $sites, $food, $thrift, $day];
    }

    private function createFoodBookingOnFoodSites(array $sites, VendorCategory $food, CarbootEvent $event, EventDay $day): Booking
    {
        $vendor = $this->user('community');
        $space = $this->space();

        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'vendor_category_id' => $food->id,
            'category_label_snapshot' => 'Food & Beverages',
            'product_category' => 'Food & Beverages',
            'product_details' => 'P38 food booking',
            'approval_status' => 'Approved',
        ]);
        $this->createdBookingIds[] = $booking->id;

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 60.00,
            'payment_status' => 'Unpaid',
        ]);
        $this->createdInvoiceIds[] = $invoice->id;

        foreach ([$sites['B01'], $sites['B02']] as $site) {
            $allocation = BookingDayAllocation::create([
                'booking_id' => $booking->id,
                'event_day_id' => $day->id,
                'event_site_id' => $site->id,
                'allocation_status' => BookingDayAllocation::STATUS_RESERVED,
                'reserved_at' => now(),
                'active_lock' => 1,
            ]);
            $this->createdAllocationIds[] = $allocation->id;
        }

        return $booking->fresh(['invoice', 'vendorCategory']);
    }

    public function test_authorization_matrix(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);

        foreach (['organizer', 'super_admin'] as $role) {
            Sanctum::actingAs($this->user($role));
            $this->getJson("/api/organizer/bookings/{$booking->id}/category-placement")->assertOk();
            $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")->assertOk();
        }

        foreach (['community', 'cmart_management'] as $role) {
            Sanctum::actingAs($this->user($role));
            $this->getJson("/api/organizer/bookings/{$booking->id}/category-placement")->assertForbidden();
            $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")->assertForbidden();
            $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
                'event_site_ids' => [$sites['B01']->id, $sites['B02']->id],
                'assignment_fingerprint' => 'stale',
            ])->assertForbidden();
        }

        auth()->forgetGuards();
        $this->getJson("/api/organizer/bookings/{$booking->id}/category-placement")->assertUnauthorized();
    }

    public function test_category_placement_reports_compatible_assignment(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        Sanctum::actingAs($this->user('organizer'));

        $response = $this->getJson("/api/organizer/bookings/{$booking->id}/category-placement")
            ->assertOk()
            ->json();

        $this->assertSame('Food & Beverages', $response['booking_category']['label']);
        $this->assertTrue($response['current_assignment']['compatible']);
        $this->assertSame(['B01', 'B02'], collect($response['current_assignment']['sites'])->pluck('label')->sort()->values()->all());
        $this->assertTrue($response['reassignment']['allowed']);
        $this->assertNotEmpty($response['reassignment']['assignment_fingerprint']);
    }

    public function test_compatible_reassignment_preserves_category_and_invoice(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        Sanctum::actingAs($this->user('organizer'));

        $options = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")->assertOk()->json();
        $fingerprint = $options['requirements']['assignment_fingerprint'];

        $response = $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['B01']->id, $sites['B02']->id],
            'assignment_fingerprint' => $fingerprint,
        ])->assertOk()->json();

        $booking->refresh();
        $this->assertSame($food->id, $booking->vendor_category_id);
        $this->assertSame('Food & Beverages', $booking->category_label_snapshot);
        $this->assertSame('60.00', number_format((float) $booking->invoice->amount, 2, '.', ''));
        $this->assertSame('Approved', $booking->approval_status);
        $this->assertSame(0, BookingCategoryOverride::where('booking_id', $booking->id)->count());
        $this->assertSame(2, $response['booking']['site_selection']['site_count']);
        $this->assertSame(2, $response['booking']['site_selection']['allocation_count']);
    }

    public function test_compatible_reassignment_moves_sites_and_diffs_allocations(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        Sanctum::actingAs($this->user('organizer'));

        $fingerprint = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->assertOk()
            ->json('requirements.assignment_fingerprint');

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['B03']->id, $sites['B04']->id],
            'assignment_fingerprint' => $fingerprint,
        ])->assertOk();

        $active = BookingDayAllocation::query()
            ->forBooking($booking->id)
            ->get()
            ->filter(fn (BookingDayAllocation $row) => $row->occupiesSite());

        $this->assertCount(2, $active);
        $this->assertSame(
            [$sites['B03']->id, $sites['B04']->id],
            $active->pluck('event_site_id')->sort()->values()->all(),
        );

        $released = BookingDayAllocation::query()
            ->where('booking_id', $booking->id)
            ->where('allocation_status', BookingDayAllocation::STATUS_RELEASED)
            ->count();
        $this->assertSame(2, $released);

        $this->assertTrue(
            BookingAuditLog::query()
                ->where('booking_id', $booking->id)
                ->where('action', 'organizer_site_reassignment')
                ->exists(),
        );
    }

    public function test_reassignment_back_reuses_released_rows_and_clears_release_metadata(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        Sanctum::actingAs($this->user('organizer'));

        $original = BookingDayAllocation::query()
            ->where('booking_id', $booking->id)
            ->whereIn('event_site_id', [$sites['B01']->id, $sites['B02']->id])
            ->orderBy('event_site_id')
            ->get()
            ->keyBy('event_site_id');

        $fingerprint = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->assertOk()
            ->json('requirements.assignment_fingerprint');

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['B03']->id, $sites['B04']->id],
            'assignment_fingerprint' => $fingerprint,
        ])->assertOk();

        foreach ([$sites['B01']->id, $sites['B02']->id] as $siteId) {
            $released = BookingDayAllocation::query()->findOrFail($original[$siteId]->id);
            $this->assertSame(BookingDayAllocation::STATUS_RELEASED, $released->allocation_status);
            $this->assertNull($released->active_lock);
            $this->assertNotNull($released->released_by);
            $this->assertNotNull($released->released_at);
            $this->assertSame(
                'organizer_site_reassignment',
                $released->release_reason,
            );
        }

        $fingerprint = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->assertOk()
            ->json('requirements.assignment_fingerprint');

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['B01']->id, $sites['B02']->id],
            'assignment_fingerprint' => $fingerprint,
        ])->assertOk();

        foreach ([$sites['B01']->id, $sites['B02']->id] as $siteId) {
            $reactivated = BookingDayAllocation::query()->findOrFail($original[$siteId]->id);
            $this->assertSame($original[$siteId]->id, $reactivated->id);
            $this->assertSame(BookingDayAllocation::STATUS_RESERVED, $reactivated->allocation_status);
            $this->assertSame(1, $reactivated->active_lock);
            $this->assertNull($reactivated->released_by);
            $this->assertNull($reactivated->released_at);
            $this->assertNull($reactivated->release_reason);
        }

        $this->assertSame(
            1,
            BookingDayAllocation::query()
                ->where('booking_id', $booking->id)
                ->where('event_day_id', $day->id)
                ->where('event_site_id', $sites['B01']->id)
                ->count(),
        );
        $this->assertSame(
            2,
            BookingAuditLog::query()
                ->where('booking_id', $booking->id)
                ->where('action', 'organizer_site_reassignment')
                ->count(),
        );
    }

    public function test_mismatch_requires_acknowledgement_and_reason(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        Sanctum::actingAs($this->user('organizer'));

        $fingerprint = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->assertOk()
            ->json('requirements.assignment_fingerprint');

        $overridesBefore = BookingCategoryOverride::count();
        $allocationsBefore = BookingDayAllocation::where('booking_id', $booking->id)->count();

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['A01']->id, $sites['A02']->id],
            'assignment_fingerprint' => $fingerprint,
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'CATEGORY_OVERRIDE_ACKNOWLEDGEMENT_REQUIRED']);

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['A01']->id, $sites['A02']->id],
            'assignment_fingerprint' => $fingerprint,
            'acknowledge_category_override' => true,
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'CATEGORY_OVERRIDE_REASON_REQUIRED']);

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['A01']->id, $sites['A02']->id],
            'assignment_fingerprint' => $fingerprint,
            'acknowledge_category_override' => true,
            'override_reason' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'CATEGORY_OVERRIDE_REASON_TOO_SHORT']);

        $this->assertSame($overridesBefore, BookingCategoryOverride::count());
        $this->assertSame($allocationsBefore, BookingDayAllocation::where('booking_id', $booking->id)->count());
    }

    public function test_mismatch_override_creates_active_record_and_preserves_booking_category(): void
    {
        [$event, $sites, $food, $thrift, $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        $organizer = $this->user('organizer');
        Sanctum::actingAs($organizer);

        $fingerprint = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->assertOk()
            ->json('requirements.assignment_fingerprint');

        $response = $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['A01']->id, $sites['A02']->id],
            'assignment_fingerprint' => $fingerprint,
            'acknowledge_category_override' => true,
            'override_reason' => 'Vendor requires placement near the event entrance for operational reasons.',
        ])->assertOk()->json();

        $booking->refresh();
        $this->assertSame($food->id, $booking->vendor_category_id);
        $this->assertSame('Food & Beverages', $booking->category_label_snapshot);
        $this->assertFalse($response['category_placement']['current_assignment']['compatible']);
        $this->assertTrue($response['category_placement']['override']['active']);

        $override = BookingCategoryOverride::query()->where('booking_id', $booking->id)->active()->first();
        $this->assertNotNull($override);
        $this->assertSame($food->id, $override->booking_category_id_snapshot);
        $this->assertSame($thrift->id, $override->assigned_category_id_snapshot);
        $this->assertSame('Vendor requires placement near the event entrance for operational reasons.', $override->reason);
        $this->assertSame($organizer->id, $override->applied_by_user_id);
    }

    public function test_compatible_reassignment_revokes_active_override(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        Sanctum::actingAs($this->user('organizer'));

        $fingerprint = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->json('requirements.assignment_fingerprint');

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['A01']->id, $sites['A02']->id],
            'assignment_fingerprint' => $fingerprint,
            'acknowledge_category_override' => true,
            'override_reason' => 'Temporary mismatch for entrance access.',
        ])->assertOk();

        $fingerprint = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->json('requirements.assignment_fingerprint');

        $response = $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['B01']->id, $sites['B02']->id],
            'assignment_fingerprint' => $fingerprint,
        ])->assertOk()->json();

        $this->assertTrue($response['category_placement']['current_assignment']['compatible']);
        $this->assertFalse($response['category_placement']['override']['active']);
        $this->assertSame(0, BookingCategoryOverride::query()->where('booking_id', $booking->id)->active()->count());
        $this->assertSame(1, BookingCategoryOverride::query()->where('booking_id', $booking->id)->where('status', 'revoked')->count());
    }

    public function test_second_mismatch_supersedes_previous_override(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        Sanctum::actingAs($this->user('organizer'));

        $fingerprint = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->json('requirements.assignment_fingerprint');

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['A01']->id, $sites['A02']->id],
            'assignment_fingerprint' => $fingerprint,
            'acknowledge_category_override' => true,
            'override_reason' => 'First mismatch reason long enough.',
        ])->assertOk();

        $fingerprint = $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->json('requirements.assignment_fingerprint');

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['A01']->id, $sites['A02']->id],
            'assignment_fingerprint' => $fingerprint,
            'acknowledge_category_override' => true,
            'override_reason' => 'Second mismatch reason with new explanation.',
        ])->assertOk();

        $this->assertSame(1, BookingCategoryOverride::query()->where('booking_id', $booking->id)->active()->count());
        $this->assertSame(1, BookingCategoryOverride::query()->where('booking_id', $booking->id)->where('status', 'superseded')->count());
        $this->assertSame(2, BookingCategoryOverride::query()->where('booking_id', $booking->id)->count());
    }

    public function test_payment_locked_blocks_reassignment(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        $booking->invoice->update(['payment_status' => 'Paid']);
        Sanctum::actingAs($this->user('organizer'));

        $this->getJson("/api/organizer/bookings/{$booking->id}/category-placement")
            ->assertOk()
            ->assertJsonPath('reassignment.allowed', false)
            ->assertJsonFragment(['code' => 'BOOKING_PAYMENT_LOCKED']);

        $this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->assertStatus(409)
            ->assertJsonFragment(['error' => 'BOOKING_PAYMENT_LOCKED']);
    }

    public function test_confirmed_allocation_blocks_reassignment(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        BookingDayAllocation::query()
            ->where('booking_id', $booking->id)
            ->update(['allocation_status' => BookingDayAllocation::STATUS_CONFIRMED]);
        Sanctum::actingAs($this->user('organizer'));

        $this->getJson("/api/organizer/bookings/{$booking->id}/category-placement")
            ->assertJsonFragment(['code' => 'BOOKING_ALLOCATION_CONFIRMED']);
    }

    public function test_stale_fingerprint_rejected_without_mutation(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        Sanctum::actingAs($this->user('organizer'));

        $overridesBefore = BookingCategoryOverride::count();

        $this->patchJson("/api/organizer/bookings/{$booking->id}/site-assignment", [
            'event_site_ids' => [$sites['A01']->id, $sites['A02']->id],
            'assignment_fingerprint' => hash('sha256', 'stale'),
            'acknowledge_category_override' => true,
            'override_reason' => 'Should not apply because fingerprint is stale.',
        ])
            ->assertStatus(409)
            ->assertJsonFragment(['error' => 'ASSIGNMENT_CHANGED']);

        $this->assertSame($overridesBefore, BookingCategoryOverride::count());
        $activeSites = BookingDayAllocation::query()
            ->forBooking($booking->id)
            ->get()
            ->filter(fn (BookingDayAllocation $row) => $row->occupiesSite())
            ->pluck('event_site_id')
            ->sort()
            ->values()
            ->all();
        $this->assertSame([$sites['B01']->id, $sites['B02']->id], $activeSites);
    }

    public function test_options_mark_compatible_and_override_flags(): void
    {
        [$event, $sites, $food, , $day] = $this->seedDualCategoryEvent();
        $booking = $this->createFoodBookingOnFoodSites($sites, $food, $event, $day);
        Sanctum::actingAs($this->user('organizer'));

        $rows = collect($this->getJson("/api/organizer/bookings/{$booking->id}/site-reassignment-options")
            ->assertOk()
            ->json('rows'))
            ->keyBy('label');

        $this->assertTrue($rows['B']['category_compatible']);
        $this->assertFalse($rows['B']['override_required']);
        $this->assertFalse($rows['A']['category_compatible']);
        $this->assertTrue($rows['A']['override_required']);
    }
}
