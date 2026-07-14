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

/**
 * Phase 2A.7 — allocation lifecycle integrated with booking workflow.
 */
class BookingAllocationLifecycleTest extends TestCase
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
            'name' => 'Lifecycle ' . $role . ' ' . uniqid(),
            'email' => 'lifecycle-' . $role . '-' . uniqid() . '@example.com',
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
            ['price' => 25.00, 'status' => 'Available'],
        );
    }

    private function createBookableEvent(): CarbootEvent
    {
        $starts = now()->addDays(15)->setTime(8, 0, 0);

        $event = CarbootEvent::create([
            'title' => 'Lifecycle Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(17, 0, 0),
            'status' => 'Available',
            'description' => 'Phase 2A.7 lifecycle test',
            'max_slots' => 50,
            'day_generation_mode' => 'calendar_days',
        ]);

        return $this->trackEvent($event);
    }

    private function seedEventLayout(CarbootEvent $event): array
    {
        $space = $this->standardSpace();
        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => 'L01',
            'row_label' => 'L',
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->createdSiteIds[] = $site->id;

        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $event->starts_at->toDateString(),
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
        $this->createdDayIds[] = $day->id;

        return [$site, $day];
    }

    private function createAllocatedBooking(User $vendor, string $approvalStatus = 'Pending_Organizer'): Booking
    {
        $event = $this->createBookableEvent();
        [$site] = $this->seedEventLayout($event);

        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => [$site->id],
            'product_category' => 'Food & Beverages',
            'product_details' => 'Lifecycle integration booking',
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

        return Booking::with('invoice', 'bookingDayAllocations')->findOrFail($bookingId);
    }

    public function test_revision_keeps_allocations_reserved(): void
    {
        $vendor = $this->createUser();
        $organizer = $this->createUser('organizer');
        $booking = $this->createAllocatedBooking($vendor);

        $allocationIds = $booking->bookingDayAllocations->pluck('id')->all();

        Sanctum::actingAs($organizer);
        $this->putJson("/api/bookings/{$booking->id}", [
            'approval_status' => 'Needs_Revision',
            'revision_comment' => 'Update details',
        ])->assertOk();

        $allocations = BookingDayAllocation::whereIn('id', $allocationIds)->get();
        $this->assertTrue($allocations->every(fn ($row) => $row->allocation_status === 'reserved'));
        $this->assertTrue($allocations->every(fn ($row) => $row->active_lock === 1));
    }

    public function test_resubmission_keeps_same_allocation_rows(): void
    {
        $vendor = $this->createUser();
        $organizer = $this->createUser('organizer');
        $booking = $this->createAllocatedBooking($vendor);
        $allocationIds = $booking->bookingDayAllocations->pluck('id')->all();

        Sanctum::actingAs($organizer);
        $this->putJson("/api/bookings/{$booking->id}", [
            'approval_status' => 'Needs_Revision',
            'revision_comment' => 'Fix typo',
        ])->assertOk();

        Sanctum::actingAs($vendor);
        $this->patchJson("/api/vendor/bookings/{$booking->id}/resubmit")
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Pending_Organizer');

        $this->assertSame(
            $allocationIds,
            BookingDayAllocation::where('booking_id', $booking->id)->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_resubmit_rejects_event_site_ids(): void
    {
        $vendor = $this->createUser();
        $organizer = $this->createUser('organizer');
        $booking = $this->createAllocatedBooking($vendor);

        Sanctum::actingAs($organizer);
        $this->putJson("/api/bookings/{$booking->id}", [
            'approval_status' => 'Needs_Revision',
            'revision_comment' => 'Fix typo',
        ])->assertOk();

        Sanctum::actingAs($vendor);
        $this->patchJson("/api/vendor/bookings/{$booking->id}/resubmit", [
            'event_site_ids' => [999],
        ])->assertStatus(422);
    }

    public function test_approval_keeps_allocations_reserved_without_confirmed_at(): void
    {
        $vendor = $this->createUser();
        $organizer = $this->createUser('organizer');
        $booking = $this->createAllocatedBooking($vendor);

        Sanctum::actingAs($organizer);
        $this->putJson("/api/bookings/{$booking->id}", [
            'approval_status' => 'Approved',
        ])->assertOk();

        $allocation = BookingDayAllocation::where('booking_id', $booking->id)->first();
        $this->assertSame('reserved', $allocation->allocation_status);
        $this->assertNull($allocation->confirmed_at);
    }

    public function test_payment_verification_confirms_all_allocations(): void
    {
        $vendor = $this->createUser();
        $organizer = $this->createUser('organizer');
        $booking = $this->createAllocatedBooking($vendor, 'Approved');
        $booking->invoice->update(['payment_status' => 'Pending Verification']);

        Sanctum::actingAs($organizer);
        $this->patchJson("/api/bookings/{$booking->id}/verify-payment")
            ->assertOk()
            ->assertJsonPath('invoice.payment_status', 'Paid');

        $allocations = BookingDayAllocation::where('booking_id', $booking->id)->get();
        $this->assertTrue($allocations->every(fn ($row) => $row->allocation_status === 'confirmed'));
        $this->assertTrue($allocations->every(fn ($row) => $row->active_lock === 1));
        $this->assertNotNull($allocations->first()->confirmed_at);
    }

    public function test_rejection_releases_allocations_with_metadata(): void
    {
        $vendor = $this->createUser();
        $organizer = $this->createUser('organizer');
        $booking = $this->createAllocatedBooking($vendor);

        Sanctum::actingAs($organizer);
        $this->putJson("/api/bookings/{$booking->id}", [
            'approval_status' => 'Rejected',
        ])->assertOk();

        $allocation = BookingDayAllocation::where('booking_id', $booking->id)->first();
        $this->assertSame('released', $allocation->allocation_status);
        $this->assertNull($allocation->active_lock);
        $this->assertNotNull($allocation->released_at);
        $this->assertSame($organizer->id, $allocation->released_by);
        $this->assertSame(BookingAllocationLifecycleService::REASON_BOOKING_REJECTED, $allocation->release_reason);
    }

    public function test_vendor_cancel_releases_reserved_allocations(): void
    {
        $vendor = $this->createUser();
        $booking = $this->createAllocatedBooking($vendor);

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Cancelled');

        $allocation = BookingDayAllocation::where('booking_id', $booking->id)->first();
        $this->assertSame('released', $allocation->allocation_status);
        $this->assertSame(BookingAllocationLifecycleService::REASON_BOOKING_CANCELLED, $allocation->release_reason);
    }

    public function test_repeated_payment_verification_is_idempotent(): void
    {
        $vendor = $this->createUser();
        $organizer = $this->createUser('organizer');
        $booking = $this->createAllocatedBooking($vendor, 'Approved');
        $booking->invoice->update(['payment_status' => 'Pending Verification']);

        Sanctum::actingAs($organizer);
        $this->patchJson("/api/bookings/{$booking->id}/verify-payment")->assertOk();

        $confirmedAt = BookingDayAllocation::where('booking_id', $booking->id)->value('confirmed_at');
        $countBefore = BookingDayAllocation::where('booking_id', $booking->id)->count();

        app(BookingAllocationLifecycleService::class)->confirmForBooking($booking->fresh());

        $this->assertSame($countBefore, BookingDayAllocation::where('booking_id', $booking->id)->count());
        $this->assertSame(
            $confirmedAt?->toDateTimeString(),
            BookingDayAllocation::where('booking_id', $booking->id)->value('confirmed_at')?->toDateTimeString(),
        );
    }

    public function test_released_allocations_cannot_be_confirmed_via_service(): void
    {
        $vendor = $this->createUser();
        $organizer = $this->createUser('organizer');
        $booking = $this->createAllocatedBooking($vendor);

        Sanctum::actingAs($organizer);
        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Rejected'])->assertOk();

        $this->expectException(\App\Exceptions\DomainConflictException::class);
        app(BookingAllocationLifecycleService::class)->confirmForBooking($booking->fresh());
    }

    public function test_repeated_release_is_idempotent(): void
    {
        $vendor = $this->createUser();
        $organizer = $this->createUser('organizer');
        $booking = $this->createAllocatedBooking($vendor);

        Sanctum::actingAs($organizer);
        $this->putJson("/api/bookings/{$booking->id}", ['approval_status' => 'Rejected'])->assertOk();

        $releasedAt = BookingDayAllocation::where('booking_id', $booking->id)->value('released_at');

        app(BookingAllocationLifecycleService::class)->releaseForBooking(
            $booking->fresh(),
            $organizer,
            BookingAllocationLifecycleService::REASON_BOOKING_REJECTED,
        );

        $this->assertSame(
            $releasedAt?->toDateTimeString(),
            BookingDayAllocation::where('booking_id', $booking->id)->value('released_at')?->toDateTimeString(),
        );
    }
}
