<?php

namespace Tests\Feature;

use App\Exceptions\DomainConflictException;
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
use App\Services\VendorBookingPresenter;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\EnsuresCanonicalLayoutForSites;
use Tests\TestCase;

class BookingWithdrawalNoRefundTest extends TestCase
{
    use CleansUpTestFixtures;
    use EnsuresCanonicalLayoutForSites;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function createUser(string $role = 'community'): User
    {
        $user = User::create([
            'name' => 'Withdraw Test ' . $role . ' ' . uniqid(),
            'email' => 'withdraw-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]);

        return $this->trackUser($user);
    }

    private function standardSpace(): Space
    {
        return Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 30.00, 'status' => 'Available'],
        );
    }

    private function createBookableEvent(): CarbootEvent
    {
        $starts = now()->addDays(12)->setTime(8, 0, 0);

        $event = CarbootEvent::create([
            'title' => 'Withdrawal Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addDay()->setTime(17, 0, 0),
            'status' => 'Open',
            'description' => 'Phase 2B.1 withdrawal test',
            'max_slots' => 50,
            'day_generation_mode' => 'calendar_days',
        ]);

        return $this->trackEvent($event);
    }

    /** @return array{0: EventSite, 1: EventDay, 2: EventSite} */
    private function seedEventLayout(CarbootEvent $event): array
    {
        $space = $this->standardSpace();
        $sites = [];

        foreach ([1, 2] as $position) {
            $site = EventSite::create([
                'carboot_event_id' => $event->id,
                'space_id' => $space->id,
                'label' => sprintf('W%02d', $position),
                'row_label' => 'W',
                'position_number' => $position,
                'grid_row' => 1,
                'grid_column' => $position,
                'display_order' => $position,
                'operational_status' => EventSite::STATUS_ACTIVE,
            ]);
            $this->createdSiteIds[] = $site->id;
            $sites[] = $this->attachSiteToFoodLayout($event, $site, 'W');
        }

        $days = [];
        for ($d = 0; $d < 2; $d++) {
            $dayStart = $event->starts_at->copy()->addDays($d);
            $day = EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $dayStart->toDateString(),
                'starts_at' => $dayStart,
                'ends_at' => $dayStart->copy()->setTime(17, 0, 0),
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => $d + 1,
            ]);
            $this->createdDayIds[] = $day->id;
            $days[] = $day;
        }

        return [$sites[0], $days[0], $sites[1]];
    }

    private function createAllocatedBooking(
        User $vendor,
        string $approvalStatus = 'Pending_Organizer',
        array $siteIds = null,
    ): Booking {
        $event = $this->createBookableEvent();
        [$siteA, , $siteB] = $this->seedEventLayout($event);
        $selectedSites = $siteIds ?? [$siteA->id, $siteB->id];

        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => $selectedSites,
            'vendor_category_id' => $this->foodVendorCategory()->id,
            'product_category' => 'Food & Beverages',
            'product_details' => 'Withdrawal test booking',
        ])->assertCreated()->json();

        $bookingId = (int) $response['booking']['id'];
        $this->createdBookingIds[] = $bookingId;
        $this->createdInvoiceIds[] = (int) $response['invoice']['id'];
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $bookingId)->pluck('id')->all(),
        );

        if ($approvalStatus !== 'Pending_Organizer') {
            Booking::whereKey($bookingId)->update(['approval_status' => $approvalStatus]);
        }

        return Booking::with('invoice', 'bookingDayAllocations', 'carbootEvent')->findOrFail($bookingId);
    }

    private function markPaidAndConfirmed(Booking $booking): Booking
    {
        $booking->invoice->update([
            'payment_status' => 'Paid',
            'payment_proof_path' => 'demo-gateway/demo_fpx',
            'payment_submitted_at' => now(),
        ]);

        app(BookingAllocationLifecycleService::class)->confirmForBooking($booking->fresh());

        return $booking->fresh(['invoice', 'bookingDayAllocations']);
    }

    public function test_unpaid_vendor_withdrawal_releases_reserved_allocations(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createAllocatedBooking($vendor);
        $invoiceAmount = $booking->invoice->amount;
        $auditBefore = BookingAuditLog::where('booking_id', $booking->id)->count();

        Sanctum::actingAs($vendor);
        $this->patchJson("/api/bookings/{$booking->id}/withdraw", [
            'withdrawal_reason' => 'Schedule conflict',
        ])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Withdrawn')
            ->assertJsonPath('booking.site_selection.allocation_status', 'released')
            ->assertJsonPath('booking.withdrawal_policy.can_withdraw', false);

        $allocations = BookingDayAllocation::where('booking_id', $booking->id)->get();
        $this->assertTrue($allocations->every(fn ($row) => $row->allocation_status === 'released'));
        $this->assertTrue($allocations->every(fn ($row) => $row->active_lock === null));
        $this->assertTrue($allocations->every(fn ($row) => $row->released_by === $vendor->id));
        $this->assertTrue($allocations->every(
            fn ($row) => $row->release_reason === BookingAllocationLifecycleService::REASON_BOOKING_WITHDRAWN,
        ));

        $invoice = Invoice::find($booking->invoice->id);
        $this->assertSame('Unpaid', $invoice->payment_status);
        $this->assertSame((float) $invoiceAmount, (float) $invoice->amount);
        $this->assertSame($auditBefore + 1, BookingAuditLog::where('booking_id', $booking->id)->count());
    }

    public function test_payment_submitted_withdrawal_requires_acknowledgement_and_preserves_proof(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createAllocatedBooking($vendor, 'Approved');
        $proofPath = 'payment-proofs/test-proof.jpg';
        $booking->invoice->update([
            'payment_status' => 'Pending Verification',
            'payment_proof_path' => $proofPath,
            'payment_submitted_at' => now(),
        ]);

        Sanctum::actingAs($vendor);
        $this->patchJson("/api/bookings/{$booking->id}/withdraw", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['acknowledge_no_refund']);

        $this->patchJson("/api/bookings/{$booking->id}/withdraw", [
            'acknowledge_no_refund' => true,
        ])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Withdrawn')
            ->assertJsonPath('booking.withdrawal_policy.payment_state', 'payment_submitted')
            ->assertJsonPath('booking.withdrawal_policy.refund_allowed', false);

        $invoice = Invoice::find($booking->invoice->id);
        $this->assertSame('Pending Verification', $invoice->payment_status);
        $this->assertSame($proofPath, $invoice->payment_proof_path);

        $allocation = BookingDayAllocation::where('booking_id', $booking->id)->first();
        $this->assertSame('released', $allocation->allocation_status);
        $this->assertNull($allocation->active_lock);
    }

    public function test_paid_withdrawal_requires_acknowledgement_and_preserves_financial_history(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createAllocatedBooking($vendor, 'Approved');
        $booking = $this->markPaidAndConfirmed($booking);
        $invoiceAmount = $booking->invoice->amount;
        $confirmedAt = BookingDayAllocation::where('booking_id', $booking->id)->value('confirmed_at');
        $proofPath = $booking->invoice->payment_proof_path;

        Sanctum::actingAs($vendor);
        $this->patchJson("/api/bookings/{$booking->id}/withdraw", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['acknowledge_no_refund']);

        $response = $this->patchJson("/api/bookings/{$booking->id}/withdraw", [
            'acknowledge_no_refund' => true,
            'withdrawal_reason' => 'Cannot attend',
        ])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Withdrawn')
            ->assertJsonPath('booking.invoice.payment_status', 'Paid')
            ->assertJsonPath('booking.site_selection.allocation_status', 'released')
            ->assertJsonPath('booking.withdrawal_policy.refund_allowed', false)
            ->assertJsonPath(
                'booking.withdrawal_policy.warning_message',
                VendorBookingPresenter::NO_REFUND_WARNING_EN,
            );

        $invoice = Invoice::find($booking->invoice->id);
        $this->assertSame('Paid', $invoice->payment_status);
        $this->assertSame($proofPath, $invoice->payment_proof_path);
        $this->assertSame((float) $invoiceAmount, (float) $invoice->amount);

        $allocations = BookingDayAllocation::where('booking_id', $booking->id)->get();
        $this->assertTrue($allocations->every(fn ($row) => $row->allocation_status === 'released'));
        $this->assertTrue($allocations->every(fn ($row) => $row->active_lock === null));
        $this->assertNotNull($allocations->first()->released_at);
        $this->assertSame(
            $confirmedAt?->toDateTimeString(),
            $allocations->first()->confirmed_at?->toDateTimeString(),
        );
    }

    public function test_withdrawn_site_becomes_available_through_availability_endpoint(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createAllocatedBooking($vendor, 'Approved');
        $booking = $this->markPaidAndConfirmed($booking);
        $eventId = $booking->carboot_event_id;
        $siteId = $booking->bookingDayAllocations->first()->event_site_id;

        Sanctum::actingAs($vendor);
        $occupied = $this->getJson(
            "/api/vendor/events/{$eventId}/site-availability?vendor_category_id=" . $this->foodVendorCategory()->id,
        )
            ->assertOk()
            ->json('sites');

        $occupiedSite = collect($occupied)->firstWhere('id', $siteId);
        $this->assertSame('occupied', $occupiedSite['availability_status']);

        $this->patchJson("/api/bookings/{$booking->id}/withdraw", [
            'acknowledge_no_refund' => true,
        ])->assertOk();

        $available = $this->getJson(
            "/api/vendor/events/{$eventId}/site-availability?vendor_category_id=" . $this->foodVendorCategory()->id,
        )
            ->assertOk()
            ->json('sites');

        $releasedSite = collect($available)->firstWhere('id', $siteId);
        $this->assertSame('available', $releasedSite['availability_status']);
        $this->assertTrue($releasedSite['is_selectable']);

        $this->assertSame(
            4,
            BookingDayAllocation::where('booking_id', $booking->id)->count(),
        );
    }

    public function test_repeated_withdrawal_is_idempotent_without_duplicate_audit(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createAllocatedBooking($vendor);

        Sanctum::actingAs($vendor);
        $this->patchJson("/api/bookings/{$booking->id}/withdraw")->assertOk();
        $withdrawnAt = Booking::find($booking->id)->withdrawn_at;
        $auditCount = BookingAuditLog::where('booking_id', $booking->id)->count();
        $releasedAt = BookingDayAllocation::where('booking_id', $booking->id)->value('released_at');

        $this->patchJson("/api/bookings/{$booking->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Withdrawn');

        $this->assertSame($auditCount, BookingAuditLog::where('booking_id', $booking->id)->count());
        $this->assertSame(
            $withdrawnAt?->toDateTimeString(),
            Booking::find($booking->id)->withdrawn_at?->toDateTimeString(),
        );
        $this->assertSame(
            $releasedAt?->toDateTimeString(),
            BookingDayAllocation::where('booking_id', $booking->id)->value('released_at')?->toDateTimeString(),
        );
    }

    public function test_terminal_bookings_cannot_be_withdrawn(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createAllocatedBooking($vendor);
        Booking::whereKey($booking->id)->update(['approval_status' => 'Rejected']);

        Sanctum::actingAs($vendor);
        $this->patchJson("/api/bookings/{$booking->id}/withdraw")
            ->assertStatus(409);

        $this->assertSame('Rejected', Booking::find($booking->id)->approval_status);
    }

    public function test_vendor_cannot_withdraw_another_vendors_booking(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $booking = $this->createAllocatedBooking($owner);

        Sanctum::actingAs($other);
        $this->patchJson("/api/bookings/{$booking->id}/withdraw")->assertForbidden();
    }

    public function test_cmart_management_cannot_withdraw(): void
    {
        $vendor = $this->createUser();
        $staff = $this->createUser('cmart_management');
        $booking = $this->createAllocatedBooking($vendor);

        Sanctum::actingAs($staff);
        $this->patchJson("/api/bookings/{$booking->id}/withdraw")->assertForbidden();
    }

    public function test_unauthenticated_withdrawal_returns_401(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createAllocatedBooking($vendor);

        auth()->forgetGuards();

        $this->patchJson("/api/bookings/{$booking->id}/withdraw")->assertUnauthorized();
    }

    public function test_allocation_release_failure_rolls_back_booking_withdrawal(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createAllocatedBooking($vendor);

        $this->mock(BookingAllocationLifecycleService::class, function ($mock) {
            $mock->shouldReceive('releaseForBooking')
                ->once()
                ->andThrow(new DomainConflictException('Release blocked for test.', 'release_blocked'));
        });

        Sanctum::actingAs($vendor);
        $this->patchJson("/api/bookings/{$booking->id}/withdraw")
            ->assertStatus(409);

        $this->assertSame('Pending_Organizer', Booking::find($booking->id)->approval_status);
        $allocation = BookingDayAllocation::where('booking_id', $booking->id)->first();
        $this->assertSame('reserved', $allocation->allocation_status);
        $this->assertSame(1, $allocation->active_lock);
        $this->assertSame(0, BookingAuditLog::where('booking_id', $booking->id)->where('to_status', 'Withdrawn')->count());
    }
}
