<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\CarbootEvent;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Staff Portal Assist Mode — booking transition policy.
 *
 * Organizers (and super admins) may assist Tier 1 staff-stage transitions on
 * Pending_Staff bookings. Actions stay recorded under the real authenticated
 * organizer account. Direct Pending_Staff -> Approved is forbidden for all roles.
 *
 * NOTE (PR2): the two-stage pipeline (Pending_Staff -> Pending_Boss) and the
 * temporary `staff` role are removed in the PR2 direct-Organizer cutover;
 * this test file is rewritten then. PR1 only migrated actor identity from the
 * legacy `manager` role to the canonical `organizer` role.
 */
class BookingStaffStageAssistTest extends TestCase
{
    private array $createdUserIds = [];
    private array $createdBookingIds = [];
    private array $createdEventIds = [];

    protected function tearDown(): void
    {
        if ($this->createdBookingIds !== []) {
            Booking::whereIn('id', $this->createdBookingIds)->delete();
            $this->createdBookingIds = [];
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
            'name' => 'Assist Test ' . ucfirst($role) . ' ' . uniqid(),
            'email' => 'assist-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
        ], $overrides));

        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createBooking(string $approvalStatus): Booking
    {
        $vendor = $this->createUser('community', ['vendor_status' => 'approved']);

        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 20.00, 'status' => 'Available'],
        );

        $event = CarbootEvent::query()->create([
            'title' => 'Assist Transition Test Event ' . uniqid(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(6),
            'status' => 'Available',
            'description' => 'Staff-stage assist transition test',
            'max_slots' => 50,
        ]);
        $this->createdEventIds[] = $event->id;

        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Staff-stage assist test booking',
            'approval_status' => $approvalStatus,
        ]);
        $this->createdBookingIds[] = $booking->id;

        Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 20.00,
            'payment_status' => 'Unpaid',
        ]);

        return $booking;
    }

    public function test_staff_can_forward_pending_staff_to_pending_boss(): void
    {
        $staff = $this->createUser('staff');
        $booking = $this->createBooking('Pending_Staff');

        Sanctum::actingAs($staff);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Pending_Boss'])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Pending_Boss');
    }

    public function test_organizer_can_assist_forward_pending_staff_to_pending_boss(): void
    {
        $organizer = $this->createUser('organizer');
        $booking = $this->createBooking('Pending_Staff');

        Sanctum::actingAs($organizer);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Pending_Boss'])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Pending_Boss');
    }

    public function test_super_admin_can_assist_forward_pending_staff_to_pending_boss(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $booking = $this->createBooking('Pending_Staff');

        Sanctum::actingAs($superAdmin);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Pending_Boss'])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Pending_Boss');
    }

    public function test_organizer_can_assist_revision_pending_staff_to_needs_revision(): void
    {
        $organizer = $this->createUser('organizer');
        $booking = $this->createBooking('Pending_Staff');

        Sanctum::actingAs($organizer);

        $this->putJson("/api/bookings/{$booking->id}", [
            'approval_status' => 'Needs_Revision',
            'revision_comment' => 'Please update your product details.',
        ])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Needs_Revision');
    }

    public function test_organizer_can_assist_reject_pending_staff(): void
    {
        $organizer = $this->createUser('organizer');
        $booking = $this->createBooking('Pending_Staff');

        Sanctum::actingAs($organizer);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Rejected'])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Rejected');
    }

    public function test_organizer_cannot_directly_approve_pending_staff(): void
    {
        $organizer = $this->createUser('organizer');
        $booking = $this->createBooking('Pending_Staff');

        Sanctum::actingAs($organizer);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Approved'])
            ->assertStatus(422);

        $this->assertSame('Pending_Staff', $booking->fresh()->approval_status);
    }

    public function test_staff_cannot_approve_pending_boss(): void
    {
        $staff = $this->createUser('staff');
        $booking = $this->createBooking('Pending_Boss');

        Sanctum::actingAs($staff);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Approved'])
            ->assertStatus(422);

        $this->assertSame('Pending_Boss', $booking->fresh()->approval_status);
    }

    public function test_community_vendor_cannot_perform_management_transitions(): void
    {
        $vendor = $this->createUser('community', ['vendor_status' => 'approved']);
        $booking = $this->createBooking('Pending_Staff');

        Sanctum::actingAs($vendor);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Pending_Boss'])
            ->assertStatus(403);

        $this->assertSame('Pending_Staff', $booking->fresh()->approval_status);
    }

    public function test_guest_cannot_perform_management_transitions(): void
    {
        $booking = $this->createBooking('Pending_Staff');

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Pending_Boss'])
            ->assertStatus(401);

        $this->assertSame('Pending_Staff', $booking->fresh()->approval_status);
    }

    public function test_organizer_assisted_forward_records_organizer_as_actor(): void
    {
        $organizer = $this->createUser('organizer');
        $booking = $this->createBooking('Pending_Staff');

        Sanctum::actingAs($organizer);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Pending_Boss'])
            ->assertOk();

        $log = BookingAuditLog::query()
            ->where('booking_id', $booking->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($organizer->id, $log->actor_user_id);
        $this->assertSame('manager_assisted_tier1_review', $log->action);
        $this->assertSame('Pending_Staff', $log->from_status);
        $this->assertSame('Pending_Boss', $log->to_status);
    }

    public function test_staff_forward_keeps_standard_status_change_audit_action(): void
    {
        $staff = $this->createUser('staff');
        $booking = $this->createBooking('Pending_Staff');

        Sanctum::actingAs($staff);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Pending_Boss'])
            ->assertOk();

        $log = BookingAuditLog::query()
            ->where('booking_id', $booking->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->actor_user_id);
        $this->assertSame('status_change', $log->action);
    }

    public function test_organizer_final_approval_from_pending_boss_still_works(): void
    {
        $organizer = $this->createUser('organizer');
        $booking = $this->createBooking('Pending_Boss');

        Sanctum::actingAs($organizer);

        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Approved');

        $log = BookingAuditLog::query()
            ->where('booking_id', $booking->id)
            ->latest('id')
            ->first();

        $this->assertSame('status_change', $log->action);
    }
}
