<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\ItemReservation;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorItem;
use App\Models\VendorItemEventListing;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Customer My Reservations — physical vendor site as collection location.
 */
class ItemReservationCollectionLocationTest extends TestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $eventIds = [];

    /** @var list<int> */
    private array $bookingIds = [];

    /** @var list<int> */
    private array $itemIds = [];

    /** @var list<int> */
    private array $reservationIds = [];

    /** @var list<int> */
    private array $siteIds = [];

    /** @var list<int> */
    private array $dayIds = [];

    /** @var list<int> */
    private array $allocationIds = [];

    /** @var array<int, int> */
    private array $vendorEventIds = [];

    protected function tearDown(): void
    {
        if ($this->allocationIds !== []) {
            BookingDayAllocation::query()->whereIn('id', $this->allocationIds)->delete();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('vendor_item_sales')) {
            DB::table('vendor_item_sales')->whereIn('vendor_item_id', $this->itemIds)->delete();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('vendor_item_event_selections')) {
            DB::table('vendor_item_event_selections')->whereIn('vendor_item_id', $this->itemIds)->delete();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('vendor_item_event_listings')) {
            DB::table('vendor_item_event_listings')->whereIn('vendor_item_id', $this->itemIds)->delete();
        }
        DB::table('item_reservation_audits')
            ->whereIn('item_reservation_id', $this->reservationIds)
            ->delete();
        DB::table('item_reservations')->whereIn('id', $this->reservationIds)->delete();
        VendorItem::query()->whereIn('id', $this->itemIds)->get()->each->delete();
        Booking::query()->whereIn('id', $this->bookingIds)->delete();
        EventSite::query()->whereIn('id', $this->siteIds)->delete();
        EventDay::query()->whereIn('id', $this->dayIds)->delete();
        CarbootEvent::query()->whereIn('id', $this->eventIds)->get()->each->delete();
        User::query()->whereIn('id', $this->userIds)->delete();

        parent::tearDown();
    }

    public function test_confirmed_reservation_exposes_single_assigned_vendor_site(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        [$event, $booking] = $this->eligibleContext($vendor, '0.00');
        $day = $this->createDay($event);
        $site = $this->createSite($event, 'A05');
        $this->allocate($booking, $day, $site);

        $reservation = $this->reserveConfirmed($reserver, $this->item($vendor, 'Single Site Item'));

        Sanctum::actingAs($reserver);
        $this->getJson('/api/reservations/me')
            ->assertOk()
            ->assertJsonPath('data.0.public_reference', $reservation->public_reference)
            ->assertJsonPath('data.0.collection.collection_location_available', true)
            ->assertJsonPath('data.0.collection.collection_sites.0.code', 'A05')
            ->assertJsonPath('data.0.collection.collection_sites.0.id', $site->id)
            ->assertJsonPath('data.0.collection.guidance', 'Collect in person at the vendor booth during the event.')
            ->assertJsonCount(1, 'data.0.collection.collection_sites');
    }

    public function test_confirmed_reservation_exposes_multiple_sites_naturally_sorted(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        [$event, $booking] = $this->eligibleContext($vendor, '0.00');
        $day = $this->createDay($event);
        $siteB = $this->createSite($event, 'A10', 2);
        $siteA = $this->createSite($event, 'A05', 1);
        $siteC = $this->createSite($event, 'A06', 3);
        $this->allocate($booking, $day, $siteB);
        $this->allocate($booking, $day, $siteC);
        $this->allocate($booking, $day, $siteA);

        $this->reserveConfirmed($reserver, $this->item($vendor, 'Multi Site Item'));

        Sanctum::actingAs($reserver);
        $response = $this->getJson('/api/reservations/me')->assertOk();

        $this->assertTrue($response->json('data.0.collection.collection_location_available'));
        $this->assertSame(
            ['A05', 'A06', 'A10'],
            collect($response->json('data.0.collection.collection_sites'))->pluck('code')->all(),
        );
    }

    public function test_vendor_site_from_another_event_is_excluded(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        [$event, $booking] = $this->eligibleContext($vendor, '0.00');
        $day = $this->createDay($event);
        $ownSite = $this->createSite($event, 'A05');
        $this->allocate($booking, $day, $ownSite);

        $otherEvent = CarbootEvent::query()->create([
            'title' => 'Other Collection Event '.uniqid(),
            'starts_at' => now()->addDays(12),
            'ends_at' => now()->addDays(12)->addHours(6),
            'status' => 'Available',
            'description' => 'Other event must not leak sites',
            'max_slots' => 20,
            'item_reservation_service_fee' => '0.00',
        ]);
        $this->eventIds[] = $otherEvent->id;
        $otherBooking = Booking::query()->create([
            'user_id' => $vendor->id,
            'space_id' => Space::defaultPhysical()->id,
            'carboot_event_id' => $otherEvent->id,
            'booking_date' => $otherEvent->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'Other event booking for the same vendor',
            'approval_status' => 'Approved',
            'site_quantity' => 1,
        ]);
        $this->bookingIds[] = $otherBooking->id;
        $otherDay = $this->createDay($otherEvent);
        $otherSite = $this->createSite($otherEvent, 'D99');
        $this->allocate($otherBooking, $otherDay, $otherSite);

        // Keep listing/reservation on the first event only.
        $this->vendorEventIds[$vendor->id] = $event->id;
        $this->reserveConfirmed($reserver, $this->item($vendor, 'Event Scope Item'));

        Sanctum::actingAs($reserver);
        $codes = collect($this->getJson('/api/reservations/me')->json('data.0.collection.collection_sites'))
            ->pluck('code')
            ->all();

        $this->assertSame(['A05'], $codes);
        $this->assertNotContains('D99', $codes);
    }

    public function test_another_vendors_site_in_same_event_is_excluded(): void
    {
        $vendor = $this->user('community');
        $otherVendor = $this->user('community');
        $reserver = $this->user('community');
        [$event, $booking] = $this->eligibleContext($vendor, '0.00');
        $day = $this->createDay($event);
        $ownSite = $this->createSite($event, 'A05');
        $this->allocate($booking, $day, $ownSite);

        $otherBooking = Booking::query()->create([
            'user_id' => $otherVendor->id,
            'space_id' => Space::defaultPhysical()->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'Other vendor booking for collection isolation',
            'approval_status' => 'Approved',
            'site_quantity' => 1,
        ]);
        $this->bookingIds[] = $otherBooking->id;
        $otherSite = $this->createSite($event, 'B12', 9);
        $this->allocate($otherBooking, $day, $otherSite);

        $this->reserveConfirmed($reserver, $this->item($vendor, 'Vendor Scope Item'));

        Sanctum::actingAs($reserver);
        $codes = collect($this->getJson('/api/reservations/me')->json('data.0.collection.collection_sites'))
            ->pluck('code')
            ->all();

        $this->assertSame(['A05'], $codes);
        $this->assertNotContains('B12', $codes);
    }

    public function test_site_quantity_without_physical_assignment_stays_unavailable(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        [$event, $booking] = $this->eligibleContext($vendor, '0.00');
        $booking->update(['site_quantity' => 2]);

        $this->assertSame(0, BookingDayAllocation::query()->where('booking_id', $booking->id)->count());

        $this->reserveConfirmed($reserver, $this->item($vendor, 'Quantity Only Item'));

        Sanctum::actingAs($reserver);
        $this->getJson('/api/reservations/me')
            ->assertOk()
            ->assertJsonPath('data.0.collection.collection_location_available', false)
            ->assertJsonPath('data.0.collection.collection_sites', [])
            ->assertJsonPath('data.0.collection.booth_number', null)
            ->assertJsonMissing(['data' => [['collection' => ['booth_number' => 'A-01']]]]);
    }

    public function test_cancelled_and_expired_do_not_expose_active_collection_guidance(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        [$event, $booking] = $this->eligibleContext($vendor, '5.00');
        $day = $this->createDay($event);
        $site = $this->createSite($event, 'A05');
        $this->allocate($booking, $day, $site);

        $cancelled = $this->reserve($reserver, $this->item($vendor, 'Cancel Collect Item'));
        Sanctum::actingAs($reserver);
        $this->postJson("/api/reservations/{$cancelled->public_reference}/cancel", [
            'reason' => 'Changed plans',
        ])->assertOk();

        $expired = $this->reserve($this->user('community'), $this->item($vendor, 'Expire Collect Item'));
        Sanctum::actingAs($this->user('organizer'));
        $this->postJson("/api/organizer/item-reservations/{$expired->public_reference}/expire", [
            'reason' => 'Hold lapsed',
        ])->assertOk();

        Sanctum::actingAs($reserver);
        $mine = $this->getJson('/api/reservations/me')->assertOk()->json('data');
        $cancelledRow = collect($mine)->firstWhere('public_reference', $cancelled->public_reference);
        $this->assertSame('cancelled', $cancelledRow['reservation_status']);
        $this->assertNull($cancelledRow['collection']['guidance']);
        $this->assertTrue($cancelledRow['collection']['collection_location_available']);

        Sanctum::actingAs($expired->reservingUser);
        $expiredMine = $this->getJson('/api/reservations/me')->assertOk()->json('data.0');
        $this->assertSame('expired', $expiredMine['reservation_status']);
        $this->assertNull($expiredMine['collection']['guidance']);
    }

    public function test_mine_endpoint_query_count_does_not_grow_per_reservation(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        [$event, $booking] = $this->eligibleContext($vendor, '0.00');
        $day = $this->createDay($event);
        $site = $this->createSite($event, 'A05');
        $this->allocate($booking, $day, $site);

        $this->reserveConfirmed($reserver, $this->item($vendor, 'NPlusOne Item A'));

        Sanctum::actingAs($reserver);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/reservations/me')->assertOk();
        $oneReservationQueries = count(DB::getQueryLog());

        $this->reserveConfirmed($reserver, $this->item($vendor, 'NPlusOne Item B'));
        $this->reserveConfirmed($reserver, $this->item($vendor, 'NPlusOne Item C'));

        DB::flushQueryLog();
        $this->getJson('/api/reservations/me')->assertOk();
        $threeReservationQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            2,
            abs($threeReservationQueries - $oneReservationQueries),
            "Mine query count grew too much: {$oneReservationQueries} -> {$threeReservationQueries}",
        );
    }

    private function user(string $role, array $overrides = []): User
    {
        $user = User::query()->create([
            'name' => 'Collection Loc User '.uniqid(),
            'email' => 'collection-loc-'.uniqid().'@example.test',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'pending',
            ...$overrides,
        ]);
        $this->userIds[] = $user->id;

        return $user;
    }

    /**
     * @return array{CarbootEvent, Booking}
     */
    private function eligibleContext(User $vendor, ?string $fee): array
    {
        $event = CarbootEvent::query()->create([
            'title' => 'Collection Loc Event '.uniqid(),
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(6),
            'status' => 'Available',
            'description' => 'Collection location test event',
            'max_slots' => 20,
            'item_reservation_service_fee' => $fee,
        ]);
        $this->eventIds[] = $event->id;

        $booking = Booking::query()->create([
            'user_id' => $vendor->id,
            'space_id' => Space::defaultPhysical()->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'Collection location eligible booking',
            'approval_status' => 'Approved',
            'site_quantity' => 1,
        ]);
        $this->bookingIds[] = $booking->id;
        $this->vendorEventIds[$vendor->id] = $event->id;

        return [$event, $booking];
    }

    private function createDay(CarbootEvent $event): EventDay
    {
        $date = $event->starts_at->toDateString();
        $day = EventDay::query()->create([
            'carboot_event_id' => $event->id,
            'operational_date' => $date,
            'starts_at' => $date.' 08:00:00',
            'ends_at' => $date.' 17:00:00',
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
        $this->dayIds[] = $day->id;

        return $day;
    }

    private function createSite(CarbootEvent $event, string $label, int $position = 1): EventSite
    {
        $site = EventSite::query()->create([
            'carboot_event_id' => $event->id,
            'space_id' => Space::defaultPhysical()->id,
            'label' => $label,
            'row_label' => substr($label, 0, 1),
            'position_number' => $position,
            'grid_row' => 1,
            'grid_column' => $position,
            'display_order' => $position,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->siteIds[] = $site->id;

        return $site;
    }

    private function allocate(Booking $booking, EventDay $day, EventSite $site): BookingDayAllocation
    {
        $allocation = BookingDayAllocation::query()->create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
            'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
            'reserved_at' => now(),
            'confirmed_at' => now(),
            'active_lock' => BookingDayAllocation::activeLockForStatus(
                BookingDayAllocation::STATUS_CONFIRMED
            ),
        ]);
        $this->allocationIds[] = $allocation->id;

        return $allocation;
    }

    private function item(User $vendor, string $name = 'Collection Loc Item'): VendorItem
    {
        $item = VendorItem::query()->create([
            'user_id' => $vendor->id,
            'name' => $name,
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => '25.00',
            'description' => 'Collection location reservation item',
            'status' => 'active',
        ]);
        $this->itemIds[] = $item->id;

        $eventId = $this->vendorEventIds[$vendor->id] ?? null;
        if ($eventId) {
            $eligibleBooking = Booking::query()
                ->where('user_id', $vendor->id)
                ->where('carboot_event_id', $eventId)
                ->where('approval_status', 'Approved')
                ->first();
            if ($eligibleBooking) {
                VendorItemEventListing::query()->updateOrCreate(
                    [
                        'vendor_item_id' => $item->id,
                        'carboot_event_id' => $eventId,
                    ],
                    [
                        'vendor_booking_id' => $eligibleBooking->id,
                        'vendor_user_id' => $vendor->id,
                        'selected_by' => $vendor->id,
                        'selected_at' => now(),
                    ],
                );
            }
        }

        return $item;
    }

    private function reserve(User $reserver, VendorItem $item): ItemReservation
    {
        Sanctum::actingAs($reserver);
        $response = $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => (int) ($this->vendorEventIds[$item->user_id] ?? 0),
        ])->assertCreated();

        $reservation = ItemReservation::query()
            ->where('public_reference', $response->json('reservation.public_reference'))
            ->firstOrFail();
        $this->reservationIds[] = $reservation->id;

        return $reservation;
    }

    private function reserveConfirmed(User $reserver, VendorItem $item): ItemReservation
    {
        return $this->reserve($reserver, $item);
    }
}
