<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\ItemReservation;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorItem;
use App\Models\VendorItemEventListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemReservationOrganizerLaunchClarityTest extends TestCase
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

    protected function tearDown(): void
    {
        if (Schema::hasTable('vendor_item_event_selections')) {
            DB::table('vendor_item_event_selections')->whereIn('vendor_item_id', $this->itemIds)->delete();
        }
        DB::table('item_reservation_audits')->whereIn('item_reservation_id', $this->reservationIds)->delete();
        DB::table('item_reservations')->whereIn('id', $this->reservationIds)->delete();
        VendorItem::query()->whereIn('id', $this->itemIds)->get()->each->delete();
        Booking::query()->whereIn('id', $this->bookingIds)->delete();
        CarbootEvent::query()->whereIn('id', $this->eventIds)->get()->each->delete();
        User::query()->whereIn('id', $this->userIds)->delete();
        parent::tearDown();
    }

    public function test_enabling_fee_alone_produces_zero_reservations_and_summary(): void
    {
        $event = $this->event('10.00');
        Sanctum::actingAs($this->user('organizer'));

        $response = $this->getJson("/api/organizer/events/{$event->id}/item-reservations")
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.event_summary.enabled', true)
            ->assertJsonPath('meta.event_summary.service_fee_amount', 10)
            ->assertJsonPath('meta.event_summary.total', 0)
            ->assertJsonPath('meta.event_summary.eligible_listings_count', 0);

        $this->assertSame([], $response->json('data'));
    }

    public function test_end_to_end_reserve_confirm_charge_and_event_scoping(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $otherVendor = $this->user('community');
        $otherReserver = $this->user('community');

        $event = $this->event('10.00');
        $otherEvent = $this->event('5.00');
        $booking = $this->approvedBooking($vendor, $event);
        $otherBooking = $this->approvedBooking($otherVendor, $otherEvent);

        $item = $this->item($vendor, $event, $booking);
        $otherItem = $this->item($otherVendor, $otherEvent, $otherBooking, 'Other Event Item');

        Sanctum::actingAs($reserver);
        $created = $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => $event->id,
        ])->assertCreated()->json('reservation');
        $this->reservationIds[] = ItemReservation::query()
            ->where('public_reference', $created['public_reference'])
            ->value('id');

        $this->assertSame('pending_charge', $created['reservation_status']);
        $this->assertSame('required', $created['charge_status']);

        Sanctum::actingAs($otherReserver);
        $otherCreated = $this->postJson('/api/reservations', [
            'vendor_item_id' => $otherItem->id,
            'carboot_event_id' => $otherEvent->id,
        ])->assertCreated()->json('reservation');
        $this->reservationIds[] = ItemReservation::query()
            ->where('public_reference', $otherCreated['public_reference'])
            ->value('id');

        Sanctum::actingAs($this->user('organizer'));
        $this->getJson("/api/organizer/events/{$event->id}/item-reservations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_reference', $created['public_reference'])
            ->assertJsonPath('meta.event_summary.total', 1)
            ->assertJsonPath('meta.event_summary.pending_charge', 1)
            ->assertJsonPath('meta.event_summary.eligible_listings_count', 1);

        $this->getJson("/api/organizer/events/{$otherEvent->id}/item-reservations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_reference', $otherCreated['public_reference']);

        $this->postJson("/api/organizer/item-reservations/{$created['public_reference']}/confirm-charge", [
            'note' => 'Cash received at counter',
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'confirmed')
            ->assertJsonPath('reservation.charge_status', 'confirmed');
    }

    public function test_free_fee_produces_confirmed_not_required(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $event = $this->event('0.00');
        $booking = $this->approvedBooking($vendor, $event);
        $item = $this->item($vendor, $event, $booking);

        Sanctum::actingAs($reserver);
        $created = $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => $event->id,
        ])->assertCreated()->json('reservation');

        $this->reservationIds[] = ItemReservation::query()
            ->where('public_reference', $created['public_reference'])
            ->value('id');

        $this->assertSame('confirmed', $created['reservation_status']);
        $this->assertSame('not_required', $created['charge_status']);
    }

    private function user(string $role): User
    {
        $user = User::query()->create([
            'name' => 'Launch User '.uniqid(),
            'email' => 'launch-'.uniqid().'@example.test',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'pending',
        ]);
        $this->userIds[] = $user->id;

        return $user;
    }

    private function event(string $fee): CarbootEvent
    {
        $event = CarbootEvent::query()->create([
            'title' => 'Launch Event '.uniqid(),
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(5),
            'status' => 'Available',
            'description' => 'Launch clarity event',
            'max_slots' => 20,
            'item_reservation_service_fee' => $fee,
        ]);
        $this->eventIds[] = $event->id;

        return $event;
    }

    private function approvedBooking(User $vendor, CarbootEvent $event): Booking
    {
        $booking = Booking::query()->create([
            'user_id' => $vendor->id,
            'space_id' => Space::defaultPhysical()->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'Launch booking',
            'approval_status' => 'Approved',
        ]);
        $this->bookingIds[] = $booking->id;

        return $booking;
    }

    private function item(User $vendor, CarbootEvent $event, Booking $booking, string $name = 'Launch Item'): VendorItem
    {
        $item = VendorItem::query()->create([
            'user_id' => $vendor->id,
            'name' => $name,
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => '15.00',
            'description' => 'Launch item',
            'status' => 'active',
        ]);
        $this->itemIds[] = $item->id;

        VendorItemEventListing::query()->updateOrCreate(
            [
                'vendor_item_id' => $item->id,
                'carboot_event_id' => $event->id,
            ],
            [
                'vendor_booking_id' => $booking->id,
                'vendor_user_id' => $vendor->id,
                'selected_by' => $vendor->id,
                'selected_at' => now(),
            ],
        );

        return $item;
    }
}
