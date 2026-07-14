<?php

namespace Tests\Feature;

use App\Exceptions\AllocationValidationException;
use App\Exceptions\DomainConflictException;
use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Services\BookingAllocationReservationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2A.6 — booking_day_allocations model + transactional reservation engine.
 */
class BookingDayAllocationReservationTest extends TestCase
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

    private function trackAllocation(BookingDayAllocation $allocation): BookingDayAllocation
    {
        $this->createdAllocationIds[] = $allocation->id;

        return $allocation;
    }

    private function createUser(string $role = 'community'): User
    {
        $user = User::create([
            'name' => 'Alloc Test ' . $role . ' ' . uniqid(),
            'email' => 'alloc-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]);
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createEvent(int $dayCount = 1): CarbootEvent
    {
        $starts = now()->addDays(10)->setTime(8, 0, 0);
        $ends = $starts->copy()->addDays(max(0, $dayCount - 1))->setTime(17, 0, 0);

        $event = CarbootEvent::query()->create([
            'title' => 'Allocation Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => 'Available',
            'description' => 'Phase 2A.6 allocation test',
            'max_slots' => 50,
            'day_generation_mode' => 'calendar_days',
        ]);
        $this->createdEventIds[] = $event->id;

        return $event;
    }

    private function standardSpace(): Space
    {
        return Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 20.00, 'status' => 'Available'],
        );
    }

    private function largeSpace(): Space
    {
        return Space::query()->firstOrCreate(
            ['space_size' => 'Large (2 Parking Lots)'],
            ['price' => 40.00, 'status' => 'Available'],
        );
    }

    private function createSite(
        CarbootEvent $event,
        Space $space,
        string $label,
        string $row,
        int $position,
        array $overrides = [],
    ): EventSite {
        $site = EventSite::create(array_merge([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => $label,
            'row_label' => $row,
            'position_number' => $position,
            'grid_row' => 1,
            'grid_column' => $position,
            'display_order' => $position,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ], $overrides));
        $this->createdSiteIds[] = $site->id;

        return $site;
    }

    private function createDay(
        CarbootEvent $event,
        string $date,
        string $status = EventDay::STATUS_ACTIVE,
        int $order = 1,
    ): EventDay {
        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => $date,
            'starts_at' => $date . ' 08:00:00',
            'ends_at' => $date . ' 17:00:00',
            'operational_status' => $status,
            'display_order' => $order,
        ]);
        $this->createdDayIds[] = $day->id;

        return $day;
    }

    private function createBooking(User $user, CarbootEvent $event, ?Space $space = null): Booking
    {
        $space ??= $this->standardSpace();

        $booking = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'Phase 2A.6 reservation test',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => 20,
            'payment_status' => 'Unpaid',
        ]);
        $this->createdInvoiceIds[] = $invoice->id;

        return $booking;
    }

    private function service(): BookingAllocationReservationService
    {
        return app(BookingAllocationReservationService::class);
    }

    private function trackResultAllocations($result): void
    {
        foreach ($result->allocations as $allocation) {
            $this->createdAllocationIds[] = $allocation->id;
        }
    }

    // --- 22.1 Model / relationships / status-lock ---

    public function test_allocation_relationships_casts_and_status_lock_mapping(): void
    {
        $vendor = $this->createUser();
        $releaser = $this->createUser('organizer');
        $event = $this->createEvent();
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A01', 'A', 1);
        $day = $this->createDay($event, $event->starts_at->toDateString());
        $booking = $this->createBooking($vendor, $event, $space);

        $allocation = $this->trackAllocation(BookingDayAllocation::factory()->released($releaser, 'test')->create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
        ]));

        $this->assertTrue($booking->bookingDayAllocations()->whereKey($allocation->id)->exists());
        $this->assertTrue($day->bookingDayAllocations()->whereKey($allocation->id)->exists());
        $this->assertTrue($site->bookingDayAllocations()->whereKey($allocation->id)->exists());
        $this->assertTrue($allocation->booking->is($booking));
        $this->assertTrue($allocation->eventDay->is($day));
        $this->assertTrue($allocation->eventSite->is($site));
        $this->assertTrue($allocation->releasedBy->is($releaser));
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $allocation->reserved_at);
        $this->assertNull($allocation->active_lock);

        $this->assertSame(
            BookingDayAllocation::STATUSES,
            [
                BookingDayAllocation::STATUS_RESERVED,
                BookingDayAllocation::STATUS_CONFIRMED,
                BookingDayAllocation::STATUS_RELEASED,
                BookingDayAllocation::STATUS_CANCELLED,
            ]
        );
        $this->assertSame(1, BookingDayAllocation::activeLockForStatus('reserved'));
        $this->assertSame(1, BookingDayAllocation::activeLockForStatus('confirmed'));
        $this->assertNull(BookingDayAllocation::activeLockForStatus('released'));
        $this->assertNull(BookingDayAllocation::activeLockForStatus('cancelled'));
    }

    // --- 22.2 Full-event reservation ---

    public function test_one_site_one_day_creates_one_reserved_allocation(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A05', 'A', 5);
        $this->createDay($event, $event->starts_at->toDateString());
        $booking = $this->createBooking($vendor, $event, $space);

        $result = $this->service()->reserveForBooking($booking, [$site->id]);
        $this->trackResultAllocations($result);

        $this->assertCount(1, $result->allocations);
        $this->assertSame(1, $result->tapakQuantity);
        $this->assertSame(number_format((float) $space->price, 2, '.', ''), $result->amount);
        $allocation = $result->allocations->first();
        $this->assertSame(BookingDayAllocation::STATUS_RESERVED, $allocation->allocation_status);
        $this->assertSame(1, $allocation->active_lock);
    }

    public function test_two_sites_two_days_creates_four_allocations_same_sites(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(2);
        $space = $this->standardSpace();
        $a05 = $this->createSite($event, $space, 'A05', 'A', 5);
        $a06 = $this->createSite($event, $space, 'A06', 'A', 6, ['grid_column' => 6]);
        $d1 = $this->createDay($event, $event->starts_at->toDateString(), EventDay::STATUS_ACTIVE, 1);
        $d2 = $this->createDay($event, $event->starts_at->copy()->addDay()->toDateString(), EventDay::STATUS_ACTIVE, 2);
        $booking = $this->createBooking($vendor, $event, $space);

        $result = $this->service()->reserveForBooking($booking, [$a06->id, $a05->id]);
        $this->trackResultAllocations($result);

        $this->assertCount(4, $result->allocations);
        $this->assertSame(2, $result->tapakQuantity);
        $this->assertSame(number_format((float) $space->price * 2, 2, '.', ''), $result->amount);

        $pairs = $result->allocations->map(fn ($a) => $a->event_day_id . ':' . $a->event_site_id)->sort()->values();
        $expected = collect([
            $d1->id . ':' . $a05->id,
            $d1->id . ':' . $a06->id,
            $d2->id . ':' . $a05->id,
            $d2->id . ':' . $a06->id,
        ])->sort()->values();
        $this->assertEquals($expected->all(), $pairs->all());

        $timestamps = $result->allocations->pluck('reserved_at')->map->toIso8601String()->unique();
        $this->assertCount(1, $timestamps);
    }

    public function test_three_sites_four_days_creates_twelve_allocations(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(4);
        $space = $this->standardSpace();
        $sites = [
            $this->createSite($event, $space, 'A05', 'A', 5),
            $this->createSite($event, $space, 'A06', 'A', 6, ['grid_column' => 6]),
            $this->createSite($event, $space, 'A07', 'A', 7, ['grid_column' => 7]),
        ];
        for ($i = 0; $i < 4; $i++) {
            $this->createDay(
                $event,
                $event->starts_at->copy()->addDays($i)->toDateString(),
                EventDay::STATUS_ACTIVE,
                $i + 1,
            );
        }
        $booking = $this->createBooking($vendor, $event, $space);

        $result = $this->service()->reserveForBooking($booking, array_map(fn ($s) => $s->id, $sites));
        $this->trackResultAllocations($result);

        $this->assertCount(12, $result->allocations);
        $this->assertSame(3, $result->tapakQuantity);
        $this->assertSame(number_format((float) $space->price * 3, 2, '.', ''), $result->amount);
    }

    public function test_cancelled_and_disabled_days_are_excluded(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(3);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A01', 'A', 1);
        $active = $this->createDay($event, $event->starts_at->toDateString(), EventDay::STATUS_ACTIVE, 1);
        $this->createDay($event, $event->starts_at->copy()->addDay()->toDateString(), EventDay::STATUS_CANCELLED, 2);
        $this->createDay($event, $event->starts_at->copy()->addDays(2)->toDateString(), EventDay::STATUS_DISABLED, 3);
        $booking = $this->createBooking($vendor, $event, $space);

        $result = $this->service()->reserveForBooking($booking, [$site->id]);
        $this->trackResultAllocations($result);

        $this->assertCount(1, $result->allocations);
        $this->assertSame($active->id, $result->allocations->first()->event_day_id);
    }

    public function test_zero_active_event_days_is_rejected(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A01', 'A', 1);
        $this->createDay($event, $event->starts_at->toDateString(), EventDay::STATUS_CANCELLED);
        $booking = $this->createBooking($vendor, $event, $space);

        $this->expectException(AllocationValidationException::class);
        $this->service()->reserveForBooking($booking, [$site->id]);
    }

    // --- 22.3 Adjacency / type ---

    public function test_accepts_adjacent_same_row_sites(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $a05 = $this->createSite($event, $space, 'A05', 'A', 5);
        $a06 = $this->createSite($event, $space, 'A06', 'A', 6, ['grid_column' => 6]);
        $a07 = $this->createSite($event, $space, 'A07', 'A', 7, ['grid_column' => 7]);
        $this->createDay($event, $event->starts_at->toDateString());
        $booking = $this->createBooking($vendor, $event, $space);

        $result = $this->service()->reserveForBooking($booking, [$a05->id, $a06->id, $a07->id]);
        $this->trackResultAllocations($result);
        $this->assertCount(3, $result->allocations);
    }

    public function test_rejects_duplicate_missing_foreign_inactive_mixed_and_gap_sites(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $other = $this->createEvent(1);
        $space = $this->standardSpace();
        $large = $this->largeSpace();
        $a05 = $this->createSite($event, $space, 'A05', 'A', 5);
        $a06 = $this->createSite($event, $space, 'A06', 'A', 6, ['grid_column' => 6]);
        $a07 = $this->createSite($event, $space, 'A07', 'A', 7, ['grid_column' => 7]);
        $b05 = $this->createSite($event, $space, 'B05', 'B', 5, ['grid_row' => 2]);
        $unavailable = $this->createSite($event, $space, 'A08', 'A', 8, [
            'grid_column' => 8,
            'operational_status' => EventSite::STATUS_UNAVAILABLE,
        ]);
        $disabled = $this->createSite($event, $space, 'A09', 'A', 9, [
            'grid_column' => 9,
            'operational_status' => EventSite::STATUS_DISABLED,
        ]);
        $mixedType = $this->createSite($event, $large, 'A10', 'A', 10, ['grid_column' => 10]);
        $foreign = $this->createSite($other, $space, 'A05', 'A', 5);
        $this->createDay($event, $event->starts_at->toDateString());
        $this->createDay($other, $other->starts_at->toDateString());
        $booking = $this->createBooking($vendor, $event, $space);

        $cases = [
            [[$a05->id, $a05->id], 'duplicate'],
            [[999999], 'missing'],
            [[$foreign->id], 'wrong event'],
            [[$unavailable->id], 'unavailable'],
            [[$disabled->id], 'disabled'],
            [[$a05->id, $b05->id], 'mixed rows'],
            [[$a05->id, $a07->id], 'non-consecutive'],
            [[$a05->id, $mixedType->id], 'mixed type'],
            [[], 'empty'],
        ];

        foreach ($cases as [$ids, $label]) {
            try {
                $this->service()->reserveForBooking($booking, $ids);
                $this->fail("Expected validation failure for case: {$label}");
            } catch (AllocationValidationException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }

        $this->assertSame(0, BookingDayAllocation::query()->forBooking($booking->id)->count());
    }

    // --- 22.5 / 22.6 Conflicts + duplicate booking reserve ---

    public function test_reserved_and_confirmed_block_released_and_cancelled_do_not(): void
    {
        $vendor = $this->createUser();
        $otherVendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A01', 'A', 1);
        $day = $this->createDay($event, $event->starts_at->toDateString());

        $holder = $this->createBooking($vendor, $event, $space);
        $blocker = $this->trackAllocation(BookingDayAllocation::factory()->reserved()->create([
            'booking_id' => $holder->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
        ]));

        $challenger = $this->createBooking($otherVendor, $event, $space);
        try {
            $this->service()->reserveForBooking($challenger, [$site->id]);
            $this->fail('Expected conflict against reserved allocation');
        } catch (DomainConflictException $exception) {
            $this->assertSame('site_day_occupied', $exception->error);
        }
        $this->assertSame(0, BookingDayAllocation::query()->forBooking($challenger->id)->count());

        $blocker->update([
            'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'active_lock' => 1,
        ]);

        try {
            $this->service()->reserveForBooking($challenger, [$site->id]);
            $this->fail('Expected conflict against confirmed allocation');
        } catch (DomainConflictException $exception) {
            $this->assertSame('site_day_occupied', $exception->error);
        }

        $blocker->update([
            'allocation_status' => BookingDayAllocation::STATUS_RELEASED,
            'released_at' => now(),
            'active_lock' => null,
        ]);

        $result = $this->service()->reserveForBooking($challenger, [$site->id]);
        $this->trackResultAllocations($result);
        $this->assertCount(1, $result->allocations);

        // Cancelled historical + new released co-exist (unique active_lock NULL).
        $secondVendor = $this->createUser();
        $secondBooking = $this->createBooking($secondVendor, $event, $space);
        foreach (BookingDayAllocation::query()->forBooking($challenger->id)->get() as $row) {
            $row->update([
                'allocation_status' => BookingDayAllocation::STATUS_CANCELLED,
                'released_at' => now(),
                'active_lock' => null,
            ]);
        }

        $result2 = $this->service()->reserveForBooking($secondBooking, [$site->id]);
        $this->trackResultAllocations($result2);
        $this->assertCount(1, $result2->allocations);

        $historical = BookingDayAllocation::query()
            ->where('event_day_id', $day->id)
            ->where('event_site_id', $site->id)
            ->historical()
            ->count();
        $this->assertGreaterThanOrEqual(2, $historical);
    }

    public function test_conflict_rolls_back_all_attempted_allocations(): void
    {
        $vendorA = $this->createUser();
        $vendorB = $this->createUser();
        $event = $this->createEvent(2);
        $space = $this->standardSpace();
        $a05 = $this->createSite($event, $space, 'A05', 'A', 5);
        $a06 = $this->createSite($event, $space, 'A06', 'A', 6, ['grid_column' => 6]);
        $d1 = $this->createDay($event, $event->starts_at->toDateString(), EventDay::STATUS_ACTIVE, 1);
        $this->createDay($event, $event->starts_at->copy()->addDay()->toDateString(), EventDay::STATUS_ACTIVE, 2);

        $holder = $this->createBooking($vendorA, $event, $space);
        $this->trackAllocation(BookingDayAllocation::factory()->reserved()->create([
            'booking_id' => $holder->id,
            'event_day_id' => $d1->id,
            'event_site_id' => $a06->id,
        ]));

        $challenger = $this->createBooking($vendorB, $event, $space);
        $before = BookingDayAllocation::query()->count();

        try {
            $this->service()->reserveForBooking($challenger, [$a05->id, $a06->id]);
            $this->fail('Expected occupancy conflict');
        } catch (DomainConflictException $exception) {
            $this->assertSame('site_day_occupied', $exception->error);
        }

        $this->assertSame($before, BookingDayAllocation::query()->count());
        $this->assertSame(0, BookingDayAllocation::query()->forBooking($challenger->id)->count());
    }

    public function test_second_reservation_for_same_booking_is_rejected(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A01', 'A', 1);
        $this->createDay($event, $event->starts_at->toDateString());
        $booking = $this->createBooking($vendor, $event, $space);

        $first = $this->service()->reserveForBooking($booking, [$site->id]);
        $this->trackResultAllocations($first);
        $firstIds = $first->allocations->pluck('id')->sort()->values()->all();

        try {
            $this->service()->reserveForBooking($booking, [$site->id]);
            $this->fail('Expected duplicate booking allocation rejection');
        } catch (DomainConflictException $exception) {
            $this->assertSame('booking_already_allocated', $exception->error);
        }

        $remaining = BookingDayAllocation::query()->forBooking($booking->id)->pluck('id')->sort()->values()->all();
        $this->assertSame($firstIds, $remaining);
    }

    public function test_active_lock_unique_constraint_blocks_duplicate_active_occupancy(): void
    {
        $vendorA = $this->createUser();
        $vendorB = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A01', 'A', 1);
        $day = $this->createDay($event, $event->starts_at->toDateString());
        $bookingA = $this->createBooking($vendorA, $event, $space);
        $bookingB = $this->createBooking($vendorB, $event, $space);

        $this->trackAllocation(BookingDayAllocation::factory()->reserved()->create([
            'booking_id' => $bookingA->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
        ]));

        $this->expectException(QueryException::class);
        BookingDayAllocation::factory()->confirmed()->create([
            'booking_id' => $bookingB->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
        ]);
    }

    public function test_check_constraint_rejects_invalid_status_lock_pairs_via_raw_insert(): void
    {
        $vendor = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A01', 'A', 1);
        $day = $this->createDay($event, $event->starts_at->toDateString());
        $booking = $this->createBooking($vendor, $event, $space);

        try {
            DB::table('booking_day_allocations')->insert([
                'booking_id' => $booking->id,
                'event_day_id' => $day->id,
                'event_site_id' => $site->id,
                'allocation_status' => 'reserved',
                'reserved_at' => now(),
                'active_lock' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected CHECK constraint failure for reserved + NULL lock');
        } catch (QueryException $exception) {
            $this->assertTrue(
                str_contains(strtolower($exception->getMessage()), 'check')
                || ($exception->errorInfo[1] ?? null) === 4025
                || ($exception->errorInfo[0] ?? null) === '23000'
            );
        }
    }

    public function test_independent_events_may_share_labels_and_coordinates(): void
    {
        $vendor = $this->createUser();
        $eventA = $this->createEvent(1);
        $eventB = $this->createEvent(1);
        $space = $this->standardSpace();
        $siteA = $this->createSite($eventA, $space, 'A05', 'A', 5);
        $siteB = $this->createSite($eventB, $space, 'A05', 'A', 5);
        $this->createDay($eventA, $eventA->starts_at->toDateString());
        $this->createDay($eventB, $eventB->starts_at->toDateString());
        $bookingA = $this->createBooking($vendor, $eventA, $space);
        $bookingB = $this->createBooking($this->createUser(), $eventB, $space);

        $r1 = $this->service()->reserveForBooking($bookingA, [$siteA->id]);
        $r2 = $this->service()->reserveForBooking($bookingB, [$siteB->id]);
        $this->trackResultAllocations($r1);
        $this->trackResultAllocations($r2);

        $this->assertCount(1, $r1->allocations);
        $this->assertCount(1, $r2->allocations);
    }

    /**
     * Concurrency approach: sequential transactions + unique constraint as the final boundary.
     * True parallel DB clients are not reliable in this PHPUnit process; documented as limitation.
     */
    public function test_stale_availability_race_converted_to_domain_conflict(): void
    {
        $vendorA = $this->createUser();
        $vendorB = $this->createUser();
        $event = $this->createEvent(1);
        $space = $this->standardSpace();
        $site = $this->createSite($event, $space, 'A01', 'A', 1);
        $day = $this->createDay($event, $event->starts_at->toDateString());
        $bookingA = $this->createBooking($vendorA, $event, $space);
        $bookingB = $this->createBooking($vendorB, $event, $space);

        $service = $this->service();

        DB::transaction(function () use ($service, $bookingA, $site, $day) {
            // Simulate a stale "available" check succeeded for both, then first commits.
            $exists = BookingDayAllocation::query()
                ->where('event_day_id', $day->id)
                ->where('event_site_id', $site->id)
                ->activeOccupancy()
                ->exists();
            $this->assertFalse($exists);

            $result = $service->reserveForBooking($bookingA, [$site->id]);
            foreach ($result->allocations as $allocation) {
                $this->createdAllocationIds[] = $allocation->id;
            }
        });

        try {
            $service->reserveForBooking($bookingB, [$site->id]);
            $this->fail('Expected conflict after first reservation committed');
        } catch (DomainConflictException $exception) {
            $this->assertSame('site_day_occupied', $exception->error);
        }

        $active = BookingDayAllocation::query()
            ->where('event_day_id', $day->id)
            ->where('event_site_id', $site->id)
            ->activeOccupancy()
            ->count();
        $this->assertSame(1, $active);
    }
}
