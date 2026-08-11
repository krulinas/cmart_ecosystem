<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorDemoPaymentTest extends TestCase
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

    private function createUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Demo Pay Vendor ' . uniqid(),
            'email' => 'demo-pay-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'approved',
        ], $overrides));

        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createBooking(User $vendor, string $approvalStatus, string $paymentStatus = 'Unpaid'): Booking
    {
        $space = Space::defaultPhysical();

        $event = CarbootEvent::query()->create([
            'title' => 'Demo Payment Test Event ' . uniqid(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(6),
            'status' => 'Available',
            'description' => 'Demo payment test event',
            'max_slots' => 50,
        ]);
        $this->createdEventIds[] = $event->id;

        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Demo payment test booking',
            'approval_status' => $approvalStatus,
        ]);
        $this->createdBookingIds[] = $booking->id;

        Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 20.00,
            'payment_status' => $paymentStatus,
        ]);

        return $booking;
    }

    public function test_approved_unpaid_booking_can_complete_demo_payment(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createBooking($vendor, 'Approved', 'Unpaid');

        Sanctum::actingAs($vendor);

        $this->postJson("/api/vendor/bookings/{$booking->id}/demo-payment", [
            'payment_method' => 'demo_fpx',
        ])
            ->assertOk()
            ->assertJsonPath('invoice.payment_status', 'Paid')
            ->assertJsonPath('booking.approval_status', 'Approved')
            ->assertJsonPath('message', 'Payment successful. Your vendor pass is now unlocked.');

        $this->assertDatabaseHas('invoices', [
            'booking_id' => $booking->id,
            'payment_status' => 'Paid',
            'payment_proof_path' => 'demo-gateway/demo_fpx',
        ]);
    }

    public function test_vendor_can_view_own_approved_unpaid_booking_for_checkout(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createBooking($vendor, 'Approved', 'Unpaid');

        Sanctum::actingAs($vendor);

        $this->getJson("/api/vendor/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('approval_status', 'Approved')
            ->assertJsonPath('invoice.payment_status', 'Unpaid');
    }

    public function test_pending_booking_cannot_complete_demo_payment(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createBooking($vendor, 'Pending_Organizer', 'Unpaid');

        Sanctum::actingAs($vendor);

        $this->postJson("/api/vendor/bookings/{$booking->id}/demo-payment", [
            'payment_method' => 'demo_card',
        ])
            ->assertStatus(422)
            ->assertJsonPath('current_status', 'Pending_Organizer');
    }

    public function test_rejected_booking_cannot_complete_demo_payment(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createBooking($vendor, 'Rejected', 'Unpaid');

        Sanctum::actingAs($vendor);

        $this->postJson("/api/vendor/bookings/{$booking->id}/demo-payment", [
            'payment_method' => 'demo_card',
        ])
            ->assertStatus(422);
    }

    public function test_withdrawn_booking_cannot_complete_demo_payment(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createBooking($vendor, 'Withdrawn', 'Unpaid');

        Sanctum::actingAs($vendor);

        $this->postJson("/api/vendor/bookings/{$booking->id}/demo-payment", [
            'payment_method' => 'demo_card',
        ])
            ->assertStatus(422);
    }

    public function test_already_paid_booking_cannot_pay_again(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createBooking($vendor, 'Approved', 'Paid');

        Sanctum::actingAs($vendor);

        $this->postJson("/api/vendor/bookings/{$booking->id}/demo-payment", [
            'payment_method' => 'demo_card',
        ])
            ->assertStatus(422)
            ->assertJsonPath('current_payment_status', 'Paid');
    }

    public function test_vendor_cannot_pay_another_vendors_booking(): void
    {
        $owner = $this->createUser();
        $otherVendor = $this->createUser();
        $booking = $this->createBooking($owner, 'Approved', 'Unpaid');

        Sanctum::actingAs($otherVendor);

        $this->postJson("/api/vendor/bookings/{$booking->id}/demo-payment", [
            'payment_method' => 'demo_fpx',
        ])
            ->assertForbidden();
    }

    public function test_pending_verification_invoice_cannot_use_demo_payment(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createBooking($vendor, 'Approved', 'Pending Verification');

        Sanctum::actingAs($vendor);

        $this->postJson("/api/vendor/bookings/{$booking->id}/demo-payment", [
            'payment_method' => 'demo_fpx',
        ])
            ->assertStatus(422)
            ->assertJsonPath('current_payment_status', 'Pending Verification');
    }
}
