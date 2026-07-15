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
use App\Services\BookingAllocationLifecycleService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\TestCase;

class OrganizerWithdrawalReconciliationTest extends TestCase
{
    use CleansUpTestFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return $this->trackUser(User::create([
            'name' => 'Reconciliation ' . $role . ' ' . uniqid(),
            'email' => 'reconciliation-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]));
    }

    /**
     * @return array{booking: Booking, vendor: User, organizer: User, invoice: Invoice}
     */
    private function withdrawnBooking(string $paymentStatus): array
    {
        $vendor = $this->user('community');
        $organizer = $this->user('organizer');
        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 30.00, 'status' => 'Available'],
        );
        $starts = now()->addDays(15)->setTime(8, 0);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Reconciliation Event ' . uniqid(),
            'description' => 'Phase 2B.2 test',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addDay()->setTime(17, 0),
            'status' => 'Open',
            'max_slots' => 30,
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $days = collect([0, 1])->map(function (int $offset) use ($event, $starts) {
            $dayStart = $starts->copy()->addDays($offset);
            $day = EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $dayStart->toDateString(),
                'starts_at' => $dayStart,
                'ends_at' => $dayStart->copy()->setTime(17, 0),
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => $offset + 1,
            ]);
            $this->createdDayIds[] = $day->id;

            return $day;
        });

        $sites = collect([1, 2])->map(function (int $position) use ($event, $space) {
            $site = EventSite::create([
                'carboot_event_id' => $event->id,
                'space_id' => $space->id,
                'label' => sprintf('R%02d', $position),
                'row_label' => 'R',
                'position_number' => $position,
                'grid_row' => 1,
                'grid_column' => $position,
                'display_order' => $position,
                'operational_status' => EventSite::STATUS_ACTIVE,
            ]);
            $this->createdSiteIds[] = $site->id;

            return $site;
        });

        $withdrawnAt = now()->subMinute();
        $booking = Booking::create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $starts->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Organizer reconciliation fixture',
            'approval_status' => 'Withdrawn',
            'withdrawn_at' => $withdrawnAt,
            'withdrawn_by' => $vendor->id,
            'withdrawal_reason' => 'Schedule changed',
        ]);
        $this->createdBookingIds[] = $booking->id;

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 60.00,
            'payment_status' => $paymentStatus,
            'payment_proof_path' => $paymentStatus === 'Unpaid'
                ? null
                : 'private/payment-proofs/secret-proof.jpg',
            'payment_submitted_at' => $paymentStatus === 'Unpaid' ? null : now()->subHours(2),
        ]);
        $this->createdInvoiceIds[] = $invoice->id;

        foreach ($days as $day) {
            foreach ($sites as $site) {
                $allocation = BookingDayAllocation::create([
                    'booking_id' => $booking->id,
                    'event_day_id' => $day->id,
                    'event_site_id' => $site->id,
                    'allocation_status' => BookingDayAllocation::STATUS_RELEASED,
                    'reserved_at' => now()->subDay(),
                    'confirmed_at' => $paymentStatus === 'Paid' ? now()->subHours(3) : null,
                    'released_at' => $withdrawnAt,
                    'released_by' => $vendor->id,
                    'release_reason' => BookingAllocationLifecycleService::REASON_BOOKING_WITHDRAWN,
                    'active_lock' => null,
                ]);
                $this->createdAllocationIds[] = $allocation->id;
            }
        }

        BookingAuditLog::create([
            'booking_id' => $booking->id,
            'actor_user_id' => $vendor->id,
            'action' => 'vendor_submitted_booking',
            'from_status' => 'New',
            'to_status' => 'Pending_Organizer',
        ]);
        BookingAuditLog::create([
            'booking_id' => $booking->id,
            'actor_user_id' => $organizer->id,
            'action' => 'organizer_approved_booking',
            'from_status' => 'Pending_Organizer',
            'to_status' => 'Approved',
        ]);
        if ($paymentStatus === 'Paid') {
            BookingAuditLog::create([
                'booking_id' => $booking->id,
                'actor_user_id' => $organizer->id,
                'action' => 'organizer_verified_payment',
                'from_status' => 'Approved',
                'to_status' => 'Approved',
            ]);
        }
        BookingAuditLog::create([
            'booking_id' => $booking->id,
            'actor_user_id' => $vendor->id,
            'action' => 'vendor_withdraw',
            'from_status' => 'Approved',
            'to_status' => 'Withdrawn',
            'revision_comment' => '<script>alert("unsafe")</script> [no-refund policy applied; payment_state=paid]',
        ]);

        return compact('booking', 'vendor', 'organizer', 'invoice');
    }

    public function test_organizer_receives_paid_withdrawal_reconciliation_and_safe_audit(): void
    {
        $fixture = $this->withdrawnBooking('Paid');
        Sanctum::actingAs($fixture['organizer']);

        $response = $this->getJson("/api/bookings/{$fixture['booking']->id}")
            ->assertOk()
            ->assertJsonPath('withdrawal_reconciliation.payment_state', 'paid')
            ->assertJsonPath('withdrawal_reconciliation.invoice_payment_status', 'Paid')
            ->assertJsonPath('withdrawal_reconciliation.invoice_amount', '60.00')
            ->assertJsonPath('withdrawal_reconciliation.payment_proof_present', true)
            ->assertJsonPath('withdrawal_reconciliation.payment_verified', true)
            ->assertJsonPath('withdrawal_reconciliation.no_refund_applied', true)
            ->assertJsonPath('withdrawal_reconciliation.financial_history_preserved', true)
            ->assertJsonPath('withdrawal_reconciliation.allocation_status', 'released')
            ->assertJsonPath('withdrawal_reconciliation.sites_released', true)
            ->assertJsonPath('withdrawal_reconciliation.active_day_count', 2)
            ->assertJsonPath('withdrawal_reconciliation.withdrawn_by.name', $fixture['vendor']->name)
            ->assertJsonPath('audit_timeline.3.action', 'vendor_withdraw')
            ->assertJsonPath('audit_timeline.3.previous_status', 'Approved')
            ->assertJsonPath('audit_timeline.3.new_status', 'Withdrawn');

        $this->assertSame(['R01', 'R02'], $response->json('withdrawal_reconciliation.released_site_labels'));
        $this->assertNotNull($response->json('withdrawal_reconciliation.payment_verified_at'));
        $this->assertNotNull($response->json('audit_timeline.3.occurred_at'));
        $this->assertSame($fixture['organizer']->name, $response->json('withdrawal_reconciliation.payment_verified_by.name'));
        $json = $response->getContent();
        $this->assertStringNotContainsString('secret-proof.jpg', $json);
        $this->assertStringNotContainsString('active_lock', $json);
        $this->assertStringNotContainsString('<script>', $json);
        $this->assertStringNotContainsString('[no-refund policy applied', $json);
    }

    public function test_payment_submitted_and_unpaid_reconciliation_are_distinct(): void
    {
        $submitted = $this->withdrawnBooking('Pending Verification');
        Sanctum::actingAs($submitted['organizer']);
        $this->getJson("/api/bookings/{$submitted['booking']->id}")
            ->assertOk()
            ->assertJsonPath('withdrawal_reconciliation.payment_state', 'payment_submitted')
            ->assertJsonPath('withdrawal_reconciliation.invoice_payment_status', 'Pending Verification')
            ->assertJsonPath('withdrawal_reconciliation.payment_verified', false)
            ->assertJsonPath('withdrawal_reconciliation.no_refund_applied', true)
            ->assertJsonPath('audit_timeline.2.summary', 'Vendor withdrew after submitting payment proof · No refund policy applied · Sites released');

        $unpaid = $this->withdrawnBooking('Unpaid');
        Sanctum::actingAs($unpaid['organizer']);
        $this->getJson("/api/bookings/{$unpaid['booking']->id}")
            ->assertOk()
            ->assertJsonPath('withdrawal_reconciliation.payment_state', 'unpaid')
            ->assertJsonPath('withdrawal_reconciliation.payment_proof_present', false)
            ->assertJsonPath('withdrawal_reconciliation.no_refund_applied', false)
            ->assertJsonPath('audit_timeline.2.summary', 'Vendor withdrew before payment · Sites released');
    }

    public function test_reconciliation_read_is_non_mutating(): void
    {
        $fixture = $this->withdrawnBooking('Pending Verification');
        $auditCount = BookingAuditLog::where('booking_id', $fixture['booking']->id)->count();
        $invoiceUpdatedAt = $fixture['invoice']->updated_at->toISOString();
        $releasedAt = BookingDayAllocation::where('booking_id', $fixture['booking']->id)
            ->orderBy('id')
            ->value('released_at');

        Sanctum::actingAs($fixture['organizer']);
        $this->getJson("/api/bookings/{$fixture['booking']->id}")->assertOk();
        $this->getJson("/api/bookings/{$fixture['booking']->id}")->assertOk();

        $this->assertSame($auditCount, BookingAuditLog::where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame($invoiceUpdatedAt, $fixture['invoice']->fresh()->updated_at->toISOString());
        $this->assertEquals(
            $releasedAt,
            BookingDayAllocation::where('booking_id', $fixture['booking']->id)->orderBy('id')->value('released_at'),
        );
    }

    public function test_vendor_response_excludes_organizer_only_reconciliation_and_private_fields(): void
    {
        $fixture = $this->withdrawnBooking('Paid');
        Sanctum::actingAs($fixture['vendor']);

        $response = $this->getJson("/api/vendor/bookings/{$fixture['booking']->id}")
            ->assertOk()
            ->assertJsonMissingPath('withdrawal_reconciliation')
            ->assertJsonMissingPath('audit_timeline')
            ->assertJsonMissingPath('audit_logs')
            ->assertJsonMissingPath('invoice.payment_proof_path');

        $this->assertStringNotContainsString($fixture['organizer']->name, $response->getContent());
    }

    public function test_governance_boundaries_for_reconciliation_detail(): void
    {
        $fixture = $this->withdrawnBooking('Paid');
        $management = $this->user('cmart_management');
        $otherVendor = $this->user('community');
        $superAdmin = $this->user('super_admin');

        Sanctum::actingAs($management);
        $this->getJson("/api/bookings/{$fixture['booking']->id}")->assertForbidden();

        Sanctum::actingAs($otherVendor);
        $this->getJson("/api/bookings/{$fixture['booking']->id}")->assertForbidden();

        Sanctum::actingAs($superAdmin);
        $this->getJson("/api/bookings/{$fixture['booking']->id}")
            ->assertOk()
            ->assertJsonPath('withdrawal_reconciliation.payment_state', 'paid');

        auth()->forgetGuards();
        $this->getJson("/api/bookings/{$fixture['booking']->id}")->assertUnauthorized();
    }

    public function test_withdrawn_payment_and_no_refund_filters_use_server_registry(): void
    {
        $submitted = $this->withdrawnBooking('Pending Verification');
        $this->withdrawnBooking('Unpaid');
        Sanctum::actingAs($submitted['organizer']);

        $this->getJson('/api/bookings?status=Withdrawn&payment_status=Pending%20Verification')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submitted['booking']->id)
            ->assertJsonPath('data.0.withdrawal_reconciliation.no_refund_applied', true);

        $this->getJson('/api/bookings?no_refund_applied=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submitted['booking']->id);
    }
}
