<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\ItemReservation;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorItem;
use App\Models\VendorItemEventListing;
use App\Models\VendorItemSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventSpecificMarketplaceFlowTest extends TestCase
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
    private array $saleIds = [];

    protected function tearDown(): void
    {
        if ($this->saleIds !== [] && Schema::hasTable('vendor_item_sales')) {
            DB::table('vendor_item_sales')->whereIn('id', $this->saleIds)->delete();
        }

        DB::table('item_reservation_audits')
            ->whereIn('item_reservation_id', $this->reservationIds)
            ->delete();
        DB::table('item_reservations')->whereIn('id', $this->reservationIds)->delete();

        if (Schema::hasTable('vendor_item_event_selections')) {
            DB::table('vendor_item_event_selections')->whereIn('vendor_item_id', $this->itemIds)->delete();
        }

        VendorItem::query()->whereIn('id', $this->itemIds)->get()->each->delete();
        Booking::query()->whereIn('id', $this->bookingIds)->delete();
        CarbootEvent::query()->whereIn('id', $this->eventIds)->get()->each->delete();
        User::query()->whereIn('id', $this->userIds)->delete();

        parent::tearDown();
    }

    public function test_vendor_cannot_select_item_without_approved_booking(): void
    {
        $vendor = $this->user('community');
        $other = $this->user('community');
        [$event] = $this->eligibleContext($other, '0.00');
        $item = $this->item($vendor, selectForEvent: false);

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/events/{$event->id}/item-listings", [
            'vendor_item_ids' => [$item->id],
        ])->assertForbidden();
    }

    public function test_unselected_item_never_appears_in_event_public_preview(): void
    {
        $vendor = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '0.00');
        $item = $this->item($vendor, selectForEvent: false);

        $this->getJson("/api/marketplace/items?carboot_event_id={$event->id}")
            ->assertOk()
            ->assertJsonMissing(['id' => $item->id]);

        $this->getJson("/api/marketplace/items/{$item->id}?carboot_event_id={$event->id}")
            ->assertNotFound();
    }

    public function test_item_selected_for_event_a_cannot_be_reserved_under_event_b(): void
    {
        $vendor = $this->user('community');
        [$eventA] = $this->eligibleContext($vendor, '0.00');
        [$eventB] = $this->eligibleContext($vendor, '0.00');
        $item = $this->item($vendor, selectForEvent: false);

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/events/{$eventA->id}/item-listings", [
            'vendor_item_ids' => [$item->id],
        ])->assertCreated();

        $reserver = $this->user('community');
        Sanctum::actingAs($reserver);
        $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => $eventB->id,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'item_not_listed_for_event');

        $created = $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => $eventA->id,
        ])->assertCreated();

        $this->trackReservationByReference($created->json('reservation.public_reference'));
        $this->assertSame($eventA->id, (int) $created->json('reservation.event.id'));
    }

    public function test_only_one_active_reservation_per_item(): void
    {
        $vendor = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '5.00');
        $item = $this->item($vendor, event: $event);

        $first = $this->user('community');
        $second = $this->user('community');

        Sanctum::actingAs($first);
        $created = $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => $event->id,
        ])->assertCreated();
        $this->trackReservationByReference($created->json('reservation.public_reference'));

        Sanctum::actingAs($second);
        $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => $event->id,
        ])->assertConflict()
            ->assertJsonPath('error', 'item_already_reserved');
    }

    public function test_reserved_sale_completion_is_idempotent_and_creates_one_sale(): void
    {
        $vendor = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '0.00');
        $item = $this->item($vendor, event: $event);
        $reserver = $this->user('community');

        Sanctum::actingAs($reserver);
        $created = $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => $event->id,
        ])->assertCreated();
        $reference = $created->json('reservation.public_reference');
        $reservation = $this->trackReservationByReference($reference);

        Sanctum::actingAs($vendor);
        $first = $this->postJson("/api/vendor/item-reservations/{$reference}/complete", [
            'final_sale_price' => 20.00,
        ])->assertOk();

        $saleId = (int) $first->json('sale.id');
        $this->saleIds[] = $saleId;

        $second = $this->postJson("/api/vendor/item-reservations/{$reference}/complete", [
            'final_sale_price' => 99.00,
        ])->assertOk();

        $this->assertSame($saleId, (int) $second->json('sale.id'));
        $this->assertSame(1, VendorItemSale::query()->where('vendor_item_id', $item->id)->count());
        $this->assertEquals('20.00', (string) VendorItemSale::query()->find($saleId)->final_sale_price);
        $this->assertSame('completed', $reservation->fresh()->reservation_status);
        $this->assertSame('sold', $item->fresh()->displayStatus());
    }

    public function test_walk_in_sale_blocked_while_active_reservation_exists(): void
    {
        $vendor = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '0.00');
        $item = $this->item($vendor, event: $event);

        Sanctum::actingAs($this->user('community'));
        $created = $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => $event->id,
        ])->assertCreated();
        $this->trackReservationByReference($created->json('reservation.public_reference'));

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/items/{$item->id}/walk-in-sale", [
            'carboot_event_id' => $event->id,
            'final_sale_price' => 15,
        ])->assertConflict()
            ->assertJsonPath('error', 'item_has_active_reservation');
    }

    public function test_sold_item_disappears_from_every_event_preview(): void
    {
        $vendor = $this->user('community');
        [$eventA] = $this->eligibleContext($vendor, '0.00');
        [$eventB] = $this->eligibleContext($vendor, '0.00');
        $item = $this->item($vendor, selectForEvent: false);

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/events/{$eventA->id}/item-listings", [
            'vendor_item_ids' => [$item->id],
        ])->assertCreated();
        $this->postJson("/api/vendor/events/{$eventB->id}/item-listings", [
            'vendor_item_ids' => [$item->id],
        ])->assertCreated();

        $this->postJson("/api/vendor/items/{$item->id}/walk-in-sale", [
            'carboot_event_id' => $eventA->id,
            'final_sale_price' => 18.50,
        ])->assertCreated();

        $sale = VendorItemSale::query()->where('vendor_item_id', $item->id)->firstOrFail();
        $this->saleIds[] = $sale->id;

        $this->assertSame(0, VendorItemEventListing::query()->where('vendor_item_id', $item->id)->count());

        foreach ([$eventA, $eventB] as $event) {
            $response = $this->getJson("/api/marketplace/items?carboot_event_id={$event->id}")->assertOk();
            $this->assertNull(collect($response->json('data'))->firstWhere('id', $item->id));
            $this->getJson("/api/marketplace/items/{$item->id}?carboot_event_id={$event->id}")
                ->assertNotFound();
        }
    }

    public function test_ended_event_no_longer_exposes_items_publicly(): void
    {
        $vendor = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '0.00');
        $item = $this->item($vendor, event: $event);

        $event->update([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $this->getJson("/api/marketplace/items?carboot_event_id={$event->id}")
            ->assertOk()
            ->assertJsonMissing(['id' => $item->id]);
        $this->getJson("/api/marketplace/items/{$item->id}?carboot_event_id={$event->id}")
            ->assertNotFound();

        Sanctum::actingAs($vendor);
        $this->getJson('/api/vendor/items')
            ->assertOk()
            ->assertJsonFragment(['id' => $item->id]);
    }

    public function test_item_with_reservation_or_sale_history_cannot_be_hard_deleted(): void
    {
        $vendor = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '0.00');
        $item = $this->item($vendor, event: $event);

        Sanctum::actingAs($this->user('community'));
        $created = $this->postJson('/api/reservations', [
            'vendor_item_id' => $item->id,
            'carboot_event_id' => $event->id,
        ])->assertCreated();
        $reservation = $this->trackReservationByReference($created->json('reservation.public_reference'));

        Sanctum::actingAs($vendor);
        $this->deleteJson("/api/vendor/items/{$item->id}")
            ->assertConflict()
            ->assertJsonPath('error', 'item_has_reservation_history');

        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/complete", [
            'final_sale_price' => 22,
        ])->assertOk();
        $this->saleIds[] = (int) VendorItemSale::query()->where('vendor_item_id', $item->id)->value('id');

        $this->deleteJson("/api/vendor/items/{$item->id}")
            ->assertConflict();
    }

    public function test_analytics_use_final_sale_prices_without_double_count(): void
    {
        $vendor = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '0.00');
        $itemA = $this->item($vendor, 'Analytics A', event: $event);
        $itemB = $this->item($vendor, 'Analytics B', event: $event);

        Sanctum::actingAs($this->user('community'));
        $created = $this->postJson('/api/reservations', [
            'vendor_item_id' => $itemA->id,
            'carboot_event_id' => $event->id,
        ])->assertCreated();
        $reference = $created->json('reservation.public_reference');
        $this->trackReservationByReference($reference);

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/item-reservations/{$reference}/complete", [
            'final_sale_price' => 30,
        ])->assertOk();
        $this->saleIds[] = (int) VendorItemSale::query()->where('vendor_item_id', $itemA->id)->value('id');

        $this->postJson("/api/vendor/items/{$itemB->id}/walk-in-sale", [
            'carboot_event_id' => $event->id,
            'final_sale_price' => 12.5,
        ])->assertCreated();
        $this->saleIds[] = (int) VendorItemSale::query()->where('vendor_item_id', $itemB->id)->value('id');

        $response = $this->getJson('/api/vendor/analytics/me')->assertOk();
        $sales = $response->json('item_sales');
        $this->assertTrue($sales['available']);
        $this->assertSame(2, $sales['items_sold']);
        $this->assertEquals(42.5, $sales['recorded_sales_total']);
        $this->assertSame(1, $sales['reserved_sales_count']);
        $this->assertSame(1, $sales['walk_in_sales_count']);
    }

    public function test_authorization_blocks_other_vendor_from_modifying_selection_or_sale(): void
    {
        $vendor = $this->user('community');
        $intruder = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '0.00');
        $this->eligibleContext($intruder, '0.00');
        $item = $this->item($vendor, event: $event);

        Sanctum::actingAs($intruder);
        $this->postJson("/api/vendor/events/{$event->id}/item-listings", [
            'vendor_item_ids' => [$item->id],
        ])->assertForbidden();

        $this->postJson("/api/vendor/items/{$item->id}/walk-in-sale", [
            'carboot_event_id' => $event->id,
            'final_sale_price' => 10,
        ])->assertForbidden();
    }

    private function user(string $role, array $overrides = []): User
    {
        $user = User::query()->create([
            'name' => 'EventMkt User '.uniqid(),
            'email' => 'eventmkt-'.uniqid().'@example.test',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'pending',
            ...$overrides,
        ]);
        $this->userIds[] = $user->id;

        return $user;
    }

    /**
     * @return array{0: CarbootEvent, 1: Booking}
     */
    private function eligibleContext(User $vendor, ?string $fee): array
    {
        $event = CarbootEvent::query()->create([
            'title' => 'EventMkt Event '.uniqid(),
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(6),
            'status' => 'Available',
            'description' => 'Event-specific marketplace event',
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
            'product_details' => 'Event marketplace booking',
            'approval_status' => 'Approved',
        ]);
        $this->bookingIds[] = $booking->id;

        return [$event, $booking];
    }

    private function item(
        User $vendor,
        string $name = 'EventMkt Item',
        string $status = 'active',
        bool $selectForEvent = true,
        ?CarbootEvent $event = null,
    ): VendorItem {
        $item = VendorItem::query()->create([
            'user_id' => $vendor->id,
            'name' => $name,
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => '25.00',
            'description' => 'Event marketplace item',
            'status' => $status,
        ]);
        $this->itemIds[] = $item->id;

        if ($selectForEvent) {
            $targetEvent = $event;
            if (! $targetEvent) {
                $booking = Booking::query()
                    ->where('user_id', $vendor->id)
                    ->where('approval_status', 'Approved')
                    ->latest('id')
                    ->first();
                $targetEvent = $booking?->carbootEvent;
            }

            if ($targetEvent) {
                $booking = Booking::query()
                    ->where('user_id', $vendor->id)
                    ->where('carboot_event_id', $targetEvent->id)
                    ->where('approval_status', 'Approved')
                    ->firstOrFail();

                VendorItemEventListing::query()->create([
                    'vendor_item_id' => $item->id,
                    'carboot_event_id' => $targetEvent->id,
                    'vendor_booking_id' => $booking->id,
                    'vendor_user_id' => $vendor->id,
                    'selected_by' => $vendor->id,
                    'selected_at' => now(),
                ]);
            }
        }

        return $item;
    }

    private function trackReservationByReference(string $reference): ItemReservation
    {
        $reservation = ItemReservation::query()
            ->where('public_reference', $reference)
            ->firstOrFail();
        $this->reservationIds[] = $reservation->id;

        return $reservation;
    }
}
