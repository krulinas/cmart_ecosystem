<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\EnsuresCanonicalLayoutForSites;
use Tests\TestCase;

/**
 * Direct Organizer booking workflow (Phase 1.3C PR2).
 */
class OrganizerBookingWorkflowTest extends TestCase
{
    use CleansUpTestFixtures;
    use EnsuresCanonicalLayoutForSites;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function createUser(string $role, array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Workflow Test ' . ucfirst($role) . ' ' . uniqid(),
            'email' => 'workflow-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => 'none',
        ], $overrides));

        return $this->trackUser($user);
    }

    private function createBooking(string $approvalStatus = 'Pending_Organizer'): Booking
    {
        $vendor = $this->createUser('community', ['vendor_status' => 'approved']);

        $space = Space::defaultPhysical();

        $event = CarbootEvent::query()->create([
            'title' => 'Organizer Workflow Test Event ' . uniqid(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(6),
            'status' => 'Available',
            'description' => 'Direct organizer workflow test',
            'max_slots' => 50,
        ]);
        $this->trackEvent($event);

        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Organizer workflow test booking',
            'approval_status' => $approvalStatus,
        ]);
        $this->createdBookingIds[] = $booking->id;

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 20.00,
            'payment_status' => 'Unpaid',
        ]);
        $this->createdInvoiceIds[] = $invoice->id;

        return $booking;
    }

    private function seedBookableEventSites(CarbootEvent $event, Space $space, int $siteCount = 1): array
    {
        $siteIds = [];

        for ($i = 1; $i <= $siteCount; $i++) {
            $site = EventSite::create([
                'carboot_event_id' => $event->id,
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
            $siteIds[] = $this->attachSiteToFoodLayout($event, $site, 'A')->id;
        }

        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $event->starts_at->toDateString(),
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
        $this->createdDayIds[] = $day->id;

        return $siteIds;
    }

    public function test_new_community_booking_defaults_to_pending_organizer(): void
    {
        $vendor = $this->createUser('community', ['vendor_status' => 'none']);
        $space = Space::defaultPhysical();

        $event = CarbootEvent::query()->create([
            'title' => 'Store Test Event ' . uniqid(),
            'starts_at' => now()->addDays(14),
            'ends_at' => now()->addDays(14)->addHours(6),
            'status' => 'Available',
            'description' => 'Booking store test',
            'max_slots' => 50,
            'day_generation_mode' => 'calendar_days',
        ]);
        $this->trackEvent($event);
        $siteIds = $this->seedBookableEventSites($event, $space);

        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$siteIds[0]],
            'vendor_category_id' => $this->foodVendorCategory()->id,
            'product_category' => 'Food & Beverages',
            'product_details' => 'New booking workflow test',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('booking.approval_status', 'Pending_Organizer');

        $bookingId = $response->json('booking.id');
        $this->createdBookingIds[] = $bookingId;

        $invoiceId = $response->json('invoice.id');
        if ($invoiceId) {
            $this->createdInvoiceIds[] = $invoiceId;
        }

        $allocationIds = BookingDayAllocation::where('booking_id', $bookingId)->pluck('id')->all();
        $this->createdAllocationIds = array_merge($this->createdAllocationIds, $allocationIds);
    }

    public function test_organizer_can_approve_pending_organizer_booking_directly(): void
    {
        $organizer = $this->createUser('organizer');
        $booking = $this->createBooking('Pending_Organizer');

        Sanctum::actingAs($organizer);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Approved');

        $log = BookingAuditLog::query()
            ->where('booking_id', $booking->id)
            ->latest('id')
            ->first();

        $this->assertSame('organizer_approved_booking', $log->action);
    }

    public function test_organizer_can_reject_pending_organizer_booking(): void
    {
        $organizer = $this->createUser('organizer');
        $booking = $this->createBooking('Pending_Organizer');

        Sanctum::actingAs($organizer);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Rejected'])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Rejected');
    }

    public function test_organizer_can_request_revision_from_pending_organizer(): void
    {
        $organizer = $this->createUser('organizer');
        $booking = $this->createBooking('Pending_Organizer');

        Sanctum::actingAs($organizer);

        $this->putJson("/api/bookings/{$booking->id}", [
            'approval_status' => 'Needs_Revision',
            'revision_comment' => 'Please update product details.',
        ])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Needs_Revision');
    }

    public function test_vendor_resubmit_returns_booking_to_pending_organizer(): void
    {
        $vendor = $this->createUser('community', ['vendor_status' => 'approved']);
        $booking = $this->createBooking('Needs_Revision');
        $booking->update(['user_id' => $vendor->id, 'revision_comment' => 'Fix details']);

        Sanctum::actingAs($vendor);

        $this->patchJson("/api/vendor/bookings/{$booking->id}/resubmit")
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Pending_Organizer');
    }

    public function test_cmart_management_cannot_approve_bookings(): void
    {
        $venue = $this->createUser('cmart_management');
        $booking = $this->createBooking('Pending_Organizer');

        Sanctum::actingAs($venue);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Approved'])
            ->assertForbidden();

        $this->assertSame('Pending_Organizer', $booking->fresh()->approval_status);
    }

    public function test_cmart_management_cannot_list_operational_bookings(): void
    {
        $venue = $this->createUser('cmart_management');

        Sanctum::actingAs($venue);

        $this->getJson('/api/bookings')->assertForbidden();
    }

    public function test_cmart_management_cannot_verify_payment(): void
    {
        $venue = $this->createUser('cmart_management');
        $booking = $this->createBooking('Approved');
        $booking->invoice->update(['payment_status' => 'Pending Verification']);

        Sanctum::actingAs($venue);

        $this->patchJson("/api/bookings/{$booking->id}/verify-payment")
            ->assertForbidden();
    }

    public function test_community_vendor_cannot_perform_organizer_transitions(): void
    {
        $vendor = $this->createUser('community', ['vendor_status' => 'approved']);
        $booking = $this->createBooking('Pending_Organizer');

        Sanctum::actingAs($vendor);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Approved'])
            ->assertStatus(403);

        $this->assertSame('Pending_Organizer', $booking->fresh()->approval_status);
    }

    public function test_super_admin_can_approve_pending_organizer_booking(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $booking = $this->createBooking('Pending_Organizer');

        Sanctum::actingAs($superAdmin);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Approved');
    }

    public function test_no_users_hold_legacy_staff_manager_or_uum_roles(): void
    {
        $this->assertSame(0, User::whereIn('role', ['staff', 'manager', 'uum'])->count());
    }
}
