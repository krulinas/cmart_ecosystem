<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\ItemReservation;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase43ManualChargeLifecycleTest extends TestCase
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

    public function test_waiver_evidence_schema_is_additive_and_history_preserving(): void
    {
        $this->assertTrue(Schema::hasColumns('item_reservations', [
            'charge_waived_by',
            'charge_waived_at',
        ]));

        $deleteRule = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
            ->where('CONSTRAINT_NAME', 'item_reservations_charge_waived_by_foreign')
            ->value('DELETE_RULE');

        $this->assertSame('SET NULL', $deleteRule);
    }

    public function test_organizer_queue_is_event_scoped_filtered_paginated_and_private(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '10.00');
        $item = $this->item($vendor);

        $otherVendor = $this->user('community');
        $otherReserver = $this->user('community');
        [$otherEvent] = $this->eligibleContext($otherVendor, '5.00');
        $otherItem = $this->item($otherVendor, 'Phase43 Other Event Item');

        $reservation = $this->reserve($reserver, $item);
        $otherReservation = $this->reserve($otherReserver, $otherItem);

        Sanctum::actingAs($this->user('organizer'));
        $queue = $this->getJson("/api/organizer/events/{$event->id}/item-reservations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_reference', $reservation->public_reference)
            ->assertJsonPath('meta.total', 1);

        $entry = $queue->json('data.0');
        $this->assertArrayNotHasKey('id', $entry);
        $this->assertArrayNotHasKey('invoice', $entry);
        $this->assertArrayNotHasKey('payment_proof_path', $entry);
        $this->assertSame($reserver->name, $entry['reserving_user']['name']);

        $this->getJson("/api/organizer/events/{$otherEvent->id}/item-reservations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_reference', $otherReservation->public_reference);

        $this->getJson("/api/organizer/events/{$event->id}/item-reservations?reservation_status=pending_charge")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/organizer/events/{$event->id}/item-reservations?reservation_status=cancelled")
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/organizer/events/{$event->id}/item-reservations?charge_status=required")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/organizer/events/{$event->id}/item-reservations?charge_status=waived")
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/organizer/events/{$event->id}/item-reservations?per_page=1&page=2")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 2);

        Sanctum::actingAs($this->user('cmart_management'));
        $this->getJson("/api/organizer/events/{$event->id}/item-reservations")->assertForbidden();
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Blocked attempt',
        ])->assertForbidden();

        Sanctum::actingAs($reserver);
        $this->getJson("/api/organizer/events/{$event->id}/item-reservations")->assertForbidden();
    }

    public function test_organizer_queue_query_count_does_not_grow_per_reservation(): void
    {
        $vendor = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '4.00');
        $this->reserve($this->user('community'), $this->item($vendor, 'Phase43 Queue Item A'));

        Sanctum::actingAs($this->user('organizer'));
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/organizer/events/{$event->id}/item-reservations")->assertOk();
        $oneReservationQueries = count(DB::getQueryLog());

        Sanctum::actingAs($this->user('community'));
        $this->reserve($this->user('community'), $this->item($vendor, 'Phase43 Queue Item B'));
        $this->reserve($this->user('community'), $this->item($vendor, 'Phase43 Queue Item C'));

        Sanctum::actingAs($this->user('organizer'));
        DB::flushQueryLog();
        $this->getJson("/api/organizer/events/{$event->id}/item-reservations")->assertOk();
        $threeReservationQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($oneReservationQueries, $threeReservationQueries);
    }

    public function test_confirmation_records_manual_payment_evidence_atomically(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '15.00');
        $reservation = $this->reserve($reserver, $this->item($vendor));
        $organizer = $this->user('organizer');

        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge")
            ->assertUnprocessable();

        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Cash received at the event booth.',
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'confirmed')
            ->assertJsonPath('reservation.charge_status', 'confirmed')
            ->assertJsonPath('reservation.charge_confirmation.note', 'Cash received at the event booth.')
            ->assertJsonPath('reservation.charge_confirmation.confirmed_by', $organizer->name);

        $fresh = $reservation->fresh();
        $this->assertSame(1, $fresh->active_lock);
        $this->assertSame($organizer->id, $fresh->charge_confirmed_by);
        $this->assertNotNull($fresh->charge_confirmed_at);

        $actions = $fresh->audits()->pluck('action');
        $this->assertSame(3, $actions->count());
        $this->assertTrue($actions->contains('charge_confirmation_recorded'));
        $this->assertTrue($actions->contains('reservation_confirmed'));

        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Second attempt',
        ])->assertConflict()
            ->assertJsonPath('error', 'reservation_not_pending_charge');
        $this->assertSame(3, $fresh->audits()->count());
        $this->assertSame('Cash received at the event booth.', $fresh->fresh()->charge_confirmation_note);
    }

    public function test_zero_fee_not_required_cannot_be_confirmed_or_waived(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '0.00');
        $reservation = $this->reserve($reserver, $this->item($vendor), expectedStatus: 'confirmed');

        Sanctum::actingAs($this->user('organizer'));
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Not applicable',
        ])->assertConflict()
            ->assertJsonPath('error', 'reservation_not_pending_charge');
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/waive-charge", [
            'reason' => 'Not applicable',
        ])->assertConflict()
            ->assertJsonPath('error', 'reservation_not_pending_charge');

        $fresh = $reservation->fresh();
        $this->assertSame('not_required', $fresh->charge_status);
        $this->assertNull($fresh->charge_confirmed_by);
        $this->assertNull($fresh->charge_waived_by);
        $this->assertSame(1, $fresh->audits()->count());
    }

    public function test_waiver_confirms_reservation_with_distinct_evidence(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '20.00');
        $reservation = $this->reserve($reserver, $this->item($vendor));
        $organizer = $this->user('organizer');

        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/waive-charge")
            ->assertUnprocessable();

        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/waive-charge", [
            'reason' => 'Community goodwill for a returning vendor.',
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'confirmed')
            ->assertJsonPath('reservation.charge_status', 'waived')
            ->assertJsonPath('reservation.charge_waiver.reason', 'Community goodwill for a returning vendor.')
            ->assertJsonPath('reservation.charge_waiver.waived_by', $organizer->name);

        $fresh = $reservation->fresh();
        $this->assertSame(1, $fresh->active_lock);
        $this->assertSame($organizer->id, $fresh->charge_waived_by);
        $this->assertNotNull($fresh->charge_waived_at);
        $this->assertNull($fresh->charge_confirmed_by);
        $this->assertNull($fresh->charge_confirmed_at);

        $actions = $fresh->audits()->pluck('action');
        $this->assertSame(3, $actions->count());
        $this->assertTrue($actions->contains('charge_waived'));
        $this->assertTrue($actions->contains('reservation_confirmed'));

        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/waive-charge", [
            'reason' => 'Repeat',
        ])->assertConflict()
            ->assertJsonPath('error', 'reservation_not_pending_charge');
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Cannot confirm a waived charge',
        ])->assertConflict();
        $this->assertSame(3, $fresh->audits()->count());
    }

    public function test_organizer_cancellation_preserves_charge_history_and_requires_acknowledgement(): void
    {
        $vendor = $this->user('community');
        $this->eligibleContext($vendor, '8.00');
        $organizer = $this->user('organizer');

        // Pending + required: charge becomes cancelled.
        $pending = $this->reserve($this->user('community'), $this->item($vendor, 'Phase43 Pending Cancel'));
        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$pending->public_reference}/cancel")
            ->assertUnprocessable();
        $this->postJson("/api/organizer/item-reservations/{$pending->public_reference}/cancel", [
            'reason' => 'Vendor requested withdrawal at the event.',
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'cancelled')
            ->assertJsonPath('reservation.charge_status', 'cancelled');
        $freshPending = $pending->fresh();
        $this->assertNull($freshPending->active_lock);
        $this->assertSame($organizer->id, $freshPending->cancelled_by);
        $this->assertSame(2, $freshPending->audits()->count());

        $this->postJson("/api/organizer/item-reservations/{$pending->public_reference}/cancel", [
            'reason' => 'Repeat',
        ])->assertConflict()
            ->assertJsonPath('error', 'reservation_not_active');
        $this->assertSame(2, $freshPending->audits()->count());

        // Confirmed charge: acknowledgement required, evidence immutable.
        $confirmed = $this->reserve($this->user('community'), $this->item($vendor, 'Phase43 Confirmed Cancel'));
        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$confirmed->public_reference}/confirm-charge", [
            'note' => 'Paid in cash.',
        ])->assertOk();
        $confirmedAt = $confirmed->fresh()->charge_confirmed_at;

        $this->postJson("/api/organizer/item-reservations/{$confirmed->public_reference}/cancel", [
            'reason' => 'Item damaged before handover.',
        ])->assertConflict()
            ->assertJsonPath('error', 'no_refund_acknowledgement_required');

        $this->postJson("/api/organizer/item-reservations/{$confirmed->public_reference}/cancel", [
            'reason' => 'Item damaged before handover.',
            'acknowledge_no_refund' => true,
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'cancelled')
            ->assertJsonPath('reservation.charge_status', 'confirmed');
        $freshConfirmed = $confirmed->fresh();
        $this->assertNull($freshConfirmed->active_lock);
        $this->assertSame('Paid in cash.', $freshConfirmed->charge_confirmation_note);
        $this->assertEquals($confirmedAt, $freshConfirmed->charge_confirmed_at);
        $this->assertSame(0, DB::table('item_reservations')->where('charge_status', 'refunded')->count());

        // Waived charge stays waived after cancellation without acknowledgement.
        $waived = $this->reserve($this->user('community'), $this->item($vendor, 'Phase43 Waived Cancel'));
        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$waived->public_reference}/waive-charge", [
            'reason' => 'Fee waived.',
        ])->assertOk();
        $this->postJson("/api/organizer/item-reservations/{$waived->public_reference}/cancel", [
            'reason' => 'Slot no longer available.',
        ])->assertOk()
            ->assertJsonPath('reservation.charge_status', 'waived');
    }

    public function test_cancelled_item_becomes_reservable_again(): void
    {
        $vendor = $this->user('community');
        $this->eligibleContext($vendor, '6.00');
        $item = $this->item($vendor);
        $reservation = $this->reserve($this->user('community'), $item);

        Sanctum::actingAs($this->user('organizer'));
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/cancel", [
            'reason' => 'Organizer decision.',
        ])->assertOk();

        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('item.is_reservable', true)
            ->assertJsonPath('item.has_active_reservation', false);
    }

    public function test_vendor_can_cancel_confirmed_reservation_with_acknowledgement_only(): void
    {
        $vendor = $this->user('community');
        $otherVendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '12.00');
        $reservation = $this->reserve($reserver, $this->item($vendor));

        Sanctum::actingAs($this->user('organizer'));
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Paid at booth.',
        ])->assertOk();

        // Reserving user remains pending-only.
        Sanctum::actingAs($reserver);
        $this->postJson("/api/reservations/{$reservation->public_reference}/cancel", [
            'reason' => 'Changed my mind',
        ])->assertConflict()
            ->assertJsonPath('error', 'reservation_not_pending');

        // Another vendor cannot reach the reservation.
        Sanctum::actingAs($otherVendor);
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/cancel", [
            'reason' => 'Not mine',
            'acknowledge_no_refund' => true,
        ])->assertNotFound();

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('error', 'cancellation_reason_required');

        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/cancel", [
            'reason' => 'Item was sold in person.',
        ])->assertConflict()
            ->assertJsonPath('error', 'no_refund_acknowledgement_required');

        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/cancel", [
            'reason' => 'Item was sold in person.',
            'acknowledge_no_refund' => true,
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'cancelled')
            ->assertJsonPath('reservation.charge_status', 'confirmed');

        $fresh = $reservation->fresh();
        $this->assertNull($fresh->active_lock);
        $this->assertSame($vendor->id, $fresh->cancelled_by);
        $this->assertSame('Paid at booth.', $fresh->charge_confirmation_note);
        // create + confirm (2) + cancel = 4 audits.
        $this->assertSame(4, $fresh->audits()->count());
    }

    public function test_manual_expiry_clears_lock_and_follows_charge_rules(): void
    {
        $vendor = $this->user('community');
        $this->eligibleContext($vendor, '9.00');
        $organizer = $this->user('organizer');

        $pending = $this->reserve($this->user('community'), $this->item($vendor, 'Phase43 Expire Pending'));
        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$pending->public_reference}/expire")
            ->assertUnprocessable();
        $this->postJson("/api/organizer/item-reservations/{$pending->public_reference}/expire", [
            'reason' => 'Reserver never arrived at the event.',
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'expired')
            ->assertJsonPath('reservation.charge_status', 'cancelled');

        $freshPending = $pending->fresh();
        $this->assertNull($freshPending->active_lock);
        $this->assertSame($organizer->id, $freshPending->expired_by);
        $this->assertNotNull($freshPending->expired_at);
        $expiryAudit = $freshPending->audits()->where('action', 'reservation_expired')->get();
        $this->assertCount(1, $expiryAudit);
        $this->assertSame('Reserver never arrived at the event.', $expiryAudit->first()->note);

        $this->postJson("/api/organizer/item-reservations/{$pending->public_reference}/expire", [
            'reason' => 'Repeat',
        ])->assertConflict()
            ->assertJsonPath('error', 'reservation_not_active');
        $this->assertSame(2, $freshPending->audits()->count());

        $confirmed = $this->reserve($this->user('community'), $this->item($vendor, 'Phase43 Expire Confirmed'));
        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$confirmed->public_reference}/confirm-charge", [
            'note' => 'Cash collected.',
        ])->assertOk();
        $this->postJson("/api/organizer/item-reservations/{$confirmed->public_reference}/expire", [
            'reason' => 'Item was never collected.',
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'expired')
            ->assertJsonPath('reservation.charge_status', 'confirmed');
        $this->assertSame('Cash collected.', $confirmed->fresh()->charge_confirmation_note);
    }

    public function test_organizer_detail_and_audit_timeline_expose_operational_evidence_only(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        [, $booking] = $this->eligibleContext($vendor, '11.00');
        $reservation = $this->reserve($reserver, $this->item($vendor));
        $organizer = $this->user('organizer');

        Sanctum::actingAs($organizer);
        $this->postJson("/api/organizer/item-reservations/{$reservation->public_reference}/confirm-charge", [
            'note' => 'Paid.',
        ])->assertOk();

        $detail = $this->getJson("/api/organizer/item-reservations/{$reservation->public_reference}")
            ->assertOk()
            ->assertJsonPath('reservation.public_reference', $reservation->public_reference)
            ->assertJsonPath('reservation.vendor.name', $vendor->name)
            ->assertJsonPath('reservation.reserving_user.name', $reserver->name)
            ->json('reservation');
        $this->assertArrayNotHasKey('id', $detail);
        $this->assertArrayNotHasKey('password', json_decode(json_encode($detail), true));
        $this->assertStringNotContainsString(
            'payment_proof',
            json_encode($detail),
        );

        $audits = $this->getJson("/api/organizer/item-reservations/{$reservation->public_reference}/audits")
            ->assertOk()
            ->json('audits');
        $this->assertCount(3, $audits);
        $this->assertSame('reservation_created', $audits[0]['action']);
        $this->assertSame('charge_confirmation_recorded', $audits[1]['action']);
        $this->assertSame('reservation_confirmed', $audits[2]['action']);
        $this->assertSame($organizer->name, $audits[1]['actor']);

        Sanctum::actingAs($this->user('cmart_management'));
        $this->getJson("/api/organizer/item-reservations/{$reservation->public_reference}")->assertForbidden();
        $this->getJson("/api/organizer/item-reservations/{$reservation->public_reference}/audits")->assertForbidden();

        Sanctum::actingAs($vendor);
        $this->getJson("/api/organizer/item-reservations/{$reservation->public_reference}")->assertForbidden();

        // Booking snapshot only; never mutated.
        $this->assertSame($booking->id, $reservation->fresh()->vendor_booking_id);
    }

    public function test_lifecycle_mutations_leave_booking_invoice_and_allocation_data_unchanged(): void
    {
        $vendor = $this->user('community');
        [, $booking] = $this->eligibleContext($vendor, '7.00');

        $invoice = $booking->invoice()->create([
            'amount' => 20.00,
            'payment_status' => 'Unpaid',
        ]);
        $bookingBefore = $booking->fresh()->getRawOriginal();
        $invoiceBefore = $invoice->fresh()->getRawOriginal();

        $organizer = $this->user('organizer');
        $mutations = [
            ['Phase43 Isolation Confirm', 'confirm-charge', ['note' => 'Paid.']],
            ['Phase43 Isolation Waive', 'waive-charge', ['reason' => 'Waived.']],
            ['Phase43 Isolation Cancel', 'cancel', ['reason' => 'Cancelled.']],
            ['Phase43 Isolation Expire', 'expire', ['reason' => 'Expired.']],
        ];

        foreach ($mutations as [$itemName, $action, $payload]) {
            $reservation = $this->reserve($this->user('community'), $this->item($vendor, $itemName));
            Sanctum::actingAs($organizer);
            $this->postJson(
                "/api/organizer/item-reservations/{$reservation->public_reference}/{$action}",
                $payload,
            )->assertOk();
        }

        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertSame($invoiceBefore, $invoice->fresh()->getRawOriginal());
        $this->assertSame(1, DB::table('invoices')->where('booking_id', $booking->id)->count());
        $this->assertSame(0, DB::table('booking_day_allocations')->where('booking_id', $booking->id)->count());
        $this->assertSame(0, DB::table('booking_audit_logs')->where('booking_id', $booking->id)->count());

        $invoice->delete();
    }

    private function user(string $role, array $overrides = []): User
    {
        $user = User::query()->create([
            'name' => 'Phase43 User '.uniqid(),
            'email' => 'phase43-'.uniqid().'@example.test',
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
            'title' => 'Phase43 Event '.uniqid(),
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(6),
            'status' => 'Available',
            'description' => 'Phase 4.3 lifecycle event',
            'max_slots' => 20,
            'item_reservation_service_fee' => $fee,
        ]);
        $this->eventIds[] = $event->id;

        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 20.00, 'status' => 'Available'],
        );
        $booking = Booking::query()->create([
            'user_id' => $vendor->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'product_category' => 'Pre-loved / Thrift',
            'product_details' => 'Phase 4.3 eligible booking',
            'approval_status' => 'Approved',
        ]);
        $this->bookingIds[] = $booking->id;

        return [$event, $booking];
    }

    private function item(
        User $vendor,
        string $name = 'Phase43 Item',
        string $status = 'active',
    ): VendorItem {
        $item = VendorItem::query()->create([
            'user_id' => $vendor->id,
            'name' => $name,
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => '25.00',
            'description' => 'Phase 4.3 lifecycle item',
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
