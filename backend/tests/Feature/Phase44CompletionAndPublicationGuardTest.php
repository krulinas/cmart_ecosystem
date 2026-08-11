<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\ItemReservation;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorItem;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase44CompletionAndPublicationGuardTest extends TestCase
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
        DB::table('item_reservation_audits')
            ->whereIn('item_reservation_id', $this->reservationIds)
            ->delete();
        DB::table('item_reservations')->whereIn('id', $this->reservationIds)->delete();
        VendorItem::query()->whereIn('id', $this->itemIds)->get()->each->delete();
        Booking::query()->whereIn('id', $this->bookingIds)->delete();
        CarbootEvent::query()->whereIn('id', $this->eventIds)->get()->each->delete();
        User::query()->whereIn('id', $this->userIds)->delete();

        parent::tearDown();
    }

    public function test_vendor_completes_owned_confirmed_reservation_and_archives_item(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '10.00');
        $item = $this->item($vendor);
        $reservation = $this->reserve($reserver, $item);

        Sanctum::actingAs($this->user('organizer'));
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Cash received.',
        ])->assertOk();

        $confirmed = $reservation->fresh();
        $chargeNote = $confirmed->charge_confirmation_note;
        $confirmedAt = $confirmed->charge_confirmed_at;
        $confirmedBy = $confirmed->charge_confirmed_by;
        $auditCountBefore = $confirmed->audits()->count();

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/complete")
            ->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'completed')
            ->assertJsonPath('reservation.charge_status', 'confirmed');

        $fresh = $reservation->fresh();
        $this->assertNull($fresh->active_lock);
        $this->assertSame($vendor->id, $fresh->completed_by);
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame('confirmed', $fresh->charge_status);
        $this->assertSame($chargeNote, $fresh->charge_confirmation_note);
        $this->assertEquals($confirmedAt, $fresh->charge_confirmed_at);
        $this->assertSame($confirmedBy, $fresh->charge_confirmed_by);
        $this->assertSame('inactive', $item->fresh()->status);
        $this->assertSame(
            1,
            $fresh->audits()->where('action', 'reservation_completed')->count(),
        );
        $this->assertSame($auditCountBefore + 1, $fresh->audits()->count());

        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/complete")
            ->assertConflict()
            ->assertJsonPath('error', 'reservation_not_confirmed');
        $this->assertSame($auditCountBefore + 1, $fresh->audits()->count());
    }

    public function test_vendor_cannot_complete_another_vendors_reservation(): void
    {
        $vendor = $this->user('community');
        $otherVendor = $this->user('community');
        $this->eligibleContext($vendor, '8.00');
        $reservation = $this->confirmedReservation($vendor);

        Sanctum::actingAs($otherVendor);
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/complete")
            ->assertNotFound();

        $this->assertSame('confirmed', $reservation->fresh()->reservation_status);
        $this->assertSame(1, $reservation->fresh()->active_lock);
    }

    public function test_organizer_completes_confirmed_reservation(): void
    {
        $vendor = $this->user('community');
        $this->eligibleContext($vendor, '12.00');
        $item = $this->item($vendor, 'Phase44 Organizer Complete Item');
        $reservation = $this->confirmedReservation($vendor, $item);
        $organizer = $this->user('organizer');

        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/complete")
            ->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'completed')
            ->assertJsonPath('reservation.completed_by', $organizer->name);

        $fresh = $reservation->fresh();
        $this->assertNull($fresh->active_lock);
        $this->assertSame($organizer->id, $fresh->completed_by);
        $this->assertSame('inactive', $item->fresh()->status);
        $this->assertSame(
            1,
            $fresh->audits()->where('action', 'reservation_completed')->count(),
        );
    }

    public function test_cmart_management_and_reserving_user_cannot_complete(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '9.00');
        $item = $this->item($vendor);
        $reservation = $this->reserve($reserver, $item);

        Sanctum::actingAs($this->user('organizer'));
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Paid.',
        ])->assertOk();

        Sanctum::actingAs($this->user('cmart_management'));
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/complete")
            ->assertForbidden();
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/complete")
            ->assertForbidden();

        Sanctum::actingAs($reserver);
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/complete")
            ->assertNotFound();
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/complete")
            ->assertForbidden();

        $this->assertSame('confirmed', $reservation->fresh()->reservation_status);
        $this->assertSame('active', $item->fresh()->status);
    }

    public function test_pending_cancelled_and_expired_cannot_complete(): void
    {
        $vendor = $this->user('community');
        $this->eligibleContext($vendor, '7.00');
        $organizer = $this->user('organizer');

        $pending = $this->reserve($this->user('community'), $this->item($vendor, 'Phase44 Pending Complete'));
        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/item-reservations/{$pending->public_reference}/complete")
            ->assertConflict()
            ->assertJsonPath('error', 'reservation_not_confirmed');

        $cancelled = $this->reserve($this->user('community'), $this->item($vendor, 'Phase44 Cancelled Complete'));
        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$cancelled->public_reference}/cancel", [
            'reason' => 'Withdrawn.',
        ])->assertOk();
        $this->postJson("/api/organizer/item-reservations/{$cancelled->public_reference}/complete")
            ->assertConflict()
            ->assertJsonPath('error', 'reservation_not_confirmed');

        $expired = $this->reserve($this->user('community'), $this->item($vendor, 'Phase44 Expired Complete'));
        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$expired->public_reference}/expire", [
            'reason' => 'No show.',
        ])->assertOk();
        $this->postJson("/api/organizer/item-reservations/{$expired->public_reference}/complete")
            ->assertConflict()
            ->assertJsonPath('error', 'reservation_not_confirmed');
    }

    public function test_zero_fee_confirmed_reservation_can_complete_preserving_not_required(): void
    {
        $vendor = $this->user('community');
        $this->eligibleContext($vendor, '0.00');
        $item = $this->item($vendor, 'Phase44 Zero Fee Complete');
        $reservation = $this->reserve(
            $this->user('community'),
            $item,
            expectedStatus: 'confirmed',
        );

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/complete")
            ->assertOk()
            ->assertJsonPath('reservation.charge_status', 'not_required');

        $this->assertSame('not_required', $reservation->fresh()->charge_status);
        $this->assertSame('inactive', $item->fresh()->status);
    }

    public function test_active_reservation_blocks_normal_unpublish_but_allows_harmless_edits(): void
    {
        $vendor = $this->user('community');
        $this->eligibleContext($vendor, '6.00');
        $item = $this->item($vendor, 'Phase44 Hold Item');
        $this->reserve($this->user('community'), $item);

        Sanctum::actingAs($vendor);
        $this->putJson("/api/vendor/items/{$item->id}", [
            'name' => 'Phase44 Hold Item Renamed',
            'category' => $item->category,
            'condition' => $item->condition,
            'pricing_type' => $item->pricing_type,
            'price' => $item->price,
            'description' => $item->description,
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('item.name', 'Phase44 Hold Item Renamed');

        $this->putJson("/api/vendor/items/{$item->id}", [
            'name' => 'Phase44 Hold Item Renamed',
            'category' => $item->category,
            'condition' => $item->condition,
            'pricing_type' => $item->pricing_type,
            'price' => $item->price,
            'description' => $item->description,
            'status' => 'inactive',
        ])->assertConflict()
            ->assertJsonPath('error', 'item_has_active_reservation');

        $this->assertSame('active', $item->fresh()->status);
    }

    public function test_completion_can_set_item_inactive_and_marketplace_becomes_unreservable(): void
    {
        $vendor = $this->user('community');
        $this->eligibleContext($vendor, '5.00');
        $item = $this->item($vendor, 'Phase44 Marketplace Archive');
        $reservation = $this->confirmedReservation($vendor, $item);

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/complete")
            ->assertOk();

        $this->assertSame('inactive', $item->fresh()->status);
        $this->getJson("/api/marketplace/items/{$item->id}")->assertNotFound();
    }

    public function test_completion_leaves_booking_invoice_and_allocation_data_unchanged(): void
    {
        $vendor = $this->user('community');
        [, $booking] = $this->eligibleContext($vendor, '11.00');
        $invoice = $booking->invoice()->create([
            'amount' => 25.00,
            'payment_status' => 'Unpaid',
        ]);
        $bookingBefore = $booking->fresh()->getRawOriginal();
        $invoiceBefore = $invoice->fresh()->getRawOriginal();

        $reservation = $this->confirmedReservation($vendor);

        Sanctum::actingAs($this->user('organizer'));
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/complete")
            ->assertOk();

        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertSame($invoiceBefore, $invoice->fresh()->getRawOriginal());
        $this->assertSame(1, DB::table('invoices')->where('booking_id', $booking->id)->count());
        $this->assertSame(0, DB::table('booking_day_allocations')->where('booking_id', $booking->id)->count());
        $this->assertSame(0, DB::table('booking_audit_logs')->where('booking_id', $booking->id)->count());

        $invoice->delete();
    }

    public function test_marketplace_detail_exposes_fee_context_and_own_item_flag(): void
    {
        $vendor = $this->user('community');
        $this->eligibleContext($vendor, '15.50');
        $item = $this->item($vendor, 'Phase44 Fee Context Item');

        $guest = $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('item.reservation_service_fee', 15.5)
            ->assertJsonPath('item.reservation_service_fee_currency', 'MYR')
            ->assertJsonPath('item.is_own_item', false)
            ->assertJsonPath('item.is_reservable', true);

        $this->assertArrayNotHasKey('user_id', $guest->json('item'));
        $this->assertArrayNotHasKey('email', $guest->json('item.vendor'));

        Sanctum::actingAs($vendor);
        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('item.is_own_item', true)
            ->assertJsonPath('item.is_reservable', true);
    }

    private function confirmedReservation(User $vendor, ?VendorItem $item = null): ItemReservation
    {
        $reservation = $this->reserve(
            $this->user('community'),
            $item ?? $this->item($vendor),
        );

        Sanctum::actingAs($this->user('organizer'));
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Paid at booth.',
        ])->assertOk();

        return $reservation->fresh();
    }

    private function user(string $role, array $overrides = []): User
    {
        $user = User::query()->create([
            'name' => 'Phase44 User '.uniqid(),
            'email' => 'phase44-'.uniqid().'@example.test',
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
            'title' => 'Phase44 Event '.uniqid(),
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(6),
            'status' => 'Available',
            'description' => 'Phase 4.4 completion event',
            'max_slots' => 20,
            'item_reservation_service_fee' => $fee,
        ]);
        $this->eventIds[] = $event->id;

        $space = Space::defaultPhysical();
        $booking = Booking::query()->create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'Phase 4.4 eligible booking',
            'approval_status' => 'Approved',
        ]);
        $this->bookingIds[] = $booking->id;

        return [$event, $booking];
    }

    private function item(
        User $vendor,
        string $name = 'Phase44 Item',
        string $status = 'active',
    ): VendorItem {
        $item = VendorItem::query()->create([
            'user_id' => $vendor->id,
            'name' => $name,
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => '25.00',
            'description' => 'Phase 4.4 completion item',
            'status' => $status,
        ]);
        $this->itemIds[] = $item->id;

        return $item;
    }

    private function reserve(
        User $reserver,
        VendorItem $item,
        string $expectedStatus = 'pending_charge',
    ): ItemReservation {
        Sanctum::actingAs($reserver);
        $response = $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertCreated()
            ->assertJsonPath('reservation.reservation_status', $expectedStatus);

        $reservation = ItemReservation::query()
            ->where('public_reference', $response->json('reservation.public_reference'))
            ->firstOrFail();
        $this->reservationIds[] = $reservation->id;

        return $reservation;
    }
}
