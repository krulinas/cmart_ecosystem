<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\ItemReservation;
use App\Models\ItemReservationAudit;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorItem;
use App\Services\ItemReservationReferenceGenerator;
use App\Services\ItemReservationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Mockery\MockInterface;
use Tests\TestCase;

class Phase42ReservationEngineTest extends TestCase
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

    public function test_schema_enforces_history_preserving_reservation_contract(): void
    {
        $this->assertTrue(Schema::hasTable('item_reservations'));
        $this->assertTrue(Schema::hasTable('item_reservation_audits'));
        $this->assertTrue(Schema::hasColumns('item_reservations', [
            'public_reference',
            'vendor_item_id',
            'reserving_user_id',
            'vendor_user_id',
            'carboot_event_id',
            'vendor_booking_id',
            'reservation_status',
            'active_lock',
            'service_fee_amount',
            'service_fee_currency',
            'charge_status',
            'item_name_snapshot',
        ]));

        $money = DB::selectOne(
            <<<'SQL'
                SELECT DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'item_reservations'
                  AND COLUMN_NAME = 'service_fee_amount'
            SQL,
        );
        $this->assertSame('decimal', $money->DATA_TYPE);
        $this->assertSame(10, (int) $money->NUMERIC_PRECISION);
        $this->assertSame(2, (int) $money->NUMERIC_SCALE);

        foreach ([
            'item_reservations_public_reference_unique' => 'UNIQUE',
            'item_reservations_item_active_lock_unique' => 'UNIQUE',
            'item_reservations_status_active_lock_check' => 'CHECK',
            'item_reservations_service_fee_non_negative' => 'CHECK',
            'item_reservations_charge_status_check' => 'CHECK',
        ] as $name => $type) {
            $constraint = DB::selectOne(
                <<<'SQL'
                    SELECT COUNT(*) AS aggregate
                    FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'item_reservations'
                      AND CONSTRAINT_NAME = ?
                      AND CONSTRAINT_TYPE LIKE ?
                SQL,
                [$name, $type.'%'],
            );
            $this->assertSame(1, (int) $constraint->aggregate, $name);
        }

        $deleteRules = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
            ->whereIn('TABLE_NAME', ['item_reservations', 'item_reservation_audits'])
            ->pluck('DELETE_RULE', 'CONSTRAINT_NAME');

        $this->assertSame('RESTRICT', $deleteRules['item_reservations_vendor_item_id_foreign']);
        $this->assertSame('RESTRICT', $deleteRules['item_reservations_reserving_user_id_foreign']);
        $this->assertSame('RESTRICT', $deleteRules['item_reservations_vendor_user_id_foreign']);
        $this->assertSame('RESTRICT', $deleteRules['item_reservations_carboot_event_id_foreign']);
        $this->assertSame('SET NULL', $deleteRules['item_reservations_vendor_booking_id_foreign']);
        $this->assertSame('RESTRICT', $deleteRules['item_reservation_audits_item_reservation_id_foreign']);
        $this->assertSame('SET NULL', $deleteRules['item_reservation_audits_actor_user_id_foreign']);
    }

    public function test_guest_and_management_roles_cannot_create_reservations(): void
    {
        $vendor = $this->user('community');
        [$event] = $this->eligibleContext($vendor, '10.00');
        $item = $this->item($vendor);

        $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertUnauthorized();

        foreach (['organizer', 'cmart_management', 'super_admin'] as $role) {
            Sanctum::actingAs($this->user($role));
            $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
                ->assertForbidden();
        }

        $this->assertDatabaseCountForEvent($event, 0);
    }

    public function test_positive_fee_creation_snapshots_context_and_is_financially_isolated(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        [$event, $booking] = $this->eligibleContext($vendor, '12.50');
        $item = $this->item($vendor, 'Snapshot Camera');
        $bookingBefore = $booking->fresh()->getRawOriginal();

        Sanctum::actingAs($reserver);
        $response = $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertCreated()
            ->assertJsonPath('reservation.reservation_status', 'pending_charge')
            ->assertJsonPath('reservation.charge_status', 'required')
            ->assertJsonPath('reservation.service_fee_amount', '12.50')
            ->assertJsonMissingPath('reservation.id')
            ->assertJsonMissingPath('reservation.vendor.email')
            ->assertJsonMissingPath('reservation.vendor.phone_number');

        $reference = $response->json('reservation.public_reference');
        $this->assertMatchesRegularExpression('/^RSV-[A-Z2-9]{8}$/', $reference);

        $reservation = ItemReservation::query()->where('public_reference', $reference)->firstOrFail();
        $this->reservationIds[] = $reservation->id;
        $this->assertSame($item->id, $reservation->vendor_item_id);
        $this->assertSame($vendor->id, $reservation->vendor_user_id);
        $this->assertSame($reserver->id, $reservation->reserving_user_id);
        $this->assertSame($event->id, $reservation->carboot_event_id);
        $this->assertSame($booking->id, $reservation->vendor_booking_id);
        $this->assertSame('Snapshot Camera', $reservation->item_name_snapshot);
        $this->assertSame(1, $reservation->active_lock);
        $this->assertSame('MYR', $reservation->service_fee_currency);
        $this->assertSame(1, $reservation->audits()->count());

        $event->update(['item_reservation_service_fee' => '99.00']);
        $item->update(['name' => 'Renamed Camera', 'status' => 'inactive']);
        $this->assertSame('12.50', $reservation->fresh()->service_fee_amount);
        $this->assertSame('Snapshot Camera', $reservation->fresh()->item_name_snapshot);
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertSame(0, DB::table('invoices')->where('booking_id', $booking->id)->count());
        $this->assertSame(0, DB::table('booking_day_allocations')->where('booking_id', $booking->id)->count());
        $this->assertSame(0, DB::table('booking_audit_logs')->where('booking_id', $booking->id)->count());
    }

    public function test_zero_fee_confirms_without_waiver_and_cannot_use_pending_cancel_path(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '0.00');
        $item = $this->item($vendor);

        Sanctum::actingAs($reserver);
        $response = $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertCreated()
            ->assertJsonPath('reservation.reservation_status', 'confirmed')
            ->assertJsonPath('reservation.charge_status', 'not_required');

        $reservation = $this->trackReservationByReference(
            $response->json('reservation.public_reference'),
        );
        $this->assertNull($reservation->charge_confirmed_by);
        $this->assertNull($reservation->charge_confirmed_at);
        $this->assertNull($reservation->charge_waive_reason);

        $this->postJson("/api/reservations/{$reservation->public_reference}/cancel")
            ->assertConflict()
            ->assertJsonPath('error', 'reservation_not_pending');
        $this->assertSame(1, $reservation->audits()->count());
    }

    public function test_creation_rejects_self_private_ineligible_and_unconfigured_items(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, null);
        $item = $this->item($vendor);

        Sanctum::actingAs($vendor);
        $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertForbidden();

        Sanctum::actingAs($reserver);
        $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'item_reservation_fee_not_configured');

        $item->update(['status' => 'inactive']);
        $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertNotFound();

        $otherVendor = $this->user('community');
        $ineligible = $this->item($otherVendor);
        $this->postJson('/api/reservations', ['vendor_item_id' => $ineligible->id])
            ->assertNotFound();
    }

    public function test_application_and_database_allow_only_one_active_reservation(): void
    {
        $vendor = $this->user('community');
        $firstUser = $this->user('community');
        $secondUser = $this->user('community');
        [$event, $booking] = $this->eligibleContext($vendor, '5.00');
        $item = $this->item($vendor);

        Sanctum::actingAs($firstUser);
        $first = $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertCreated();
        $reservation = $this->trackReservationByReference(
            $first->json('reservation.public_reference'),
        );

        Sanctum::actingAs($secondUser);
        $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertConflict()
            ->assertJsonPath('error', 'item_already_reserved');

        $this->assertSame(1, ItemReservation::query()
            ->where('vendor_item_id', $item->id)->active()->count());
        $this->assertSame(1, $reservation->audits()->count());

        try {
            DB::table('item_reservations')->insert(
                $this->reservationRow($item, $secondUser, $vendor, $event, $booking, 'RSV-DUPLIC8'),
            );
            $this->fail('The active-lock unique constraint accepted a second active row.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1] ?? null);
            $this->assertStringContainsString(
                'item_reservations_item_active_lock_unique',
                $exception->getMessage(),
            );
        }
    }

    public function test_reference_collision_retries_without_becoming_item_conflict(): void
    {
        $firstVendor = $this->user('community');
        $firstReserver = $this->user('community');
        $this->eligibleContext($firstVendor, '3.00');
        $firstItem = $this->item($firstVendor);
        Sanctum::actingAs($firstReserver);
        $first = $this->postJson('/api/reservations', ['vendor_item_id' => $firstItem->id])
            ->assertCreated();
        $existingReference = $first->json('reservation.public_reference');
        $this->trackReservationByReference($existingReference);

        $secondVendor = $this->user('community');
        $secondReserver = $this->user('community');
        $this->eligibleContext($secondVendor, '4.00');
        $secondItem = $this->item($secondVendor);
        $newReference = 'RSV-NEWREF42';

        $this->mock(
            ItemReservationReferenceGenerator::class,
            function (MockInterface $mock) use ($existingReference, $newReference) {
                $mock->shouldReceive('generate')->twice()->andReturn(
                    $existingReference,
                    $newReference,
                );
            },
        );

        Sanctum::actingAs($secondReserver);
        $this->postJson('/api/reservations', ['vendor_item_id' => $secondItem->id])
            ->assertCreated()
            ->assertJsonPath('reservation.public_reference', $newReference);
        $this->trackReservationByReference($newReference);
    }

    public function test_reference_collision_stops_after_bounded_attempts_without_partial_write(): void
    {
        $firstVendor = $this->user('community');
        $firstReserver = $this->user('community');
        $this->eligibleContext($firstVendor, '3.00');
        $firstItem = $this->item($firstVendor);
        Sanctum::actingAs($firstReserver);
        $first = $this->postJson('/api/reservations', ['vendor_item_id' => $firstItem->id])
            ->assertCreated();
        $existingReference = $first->json('reservation.public_reference');
        $this->trackReservationByReference($existingReference);

        $secondVendor = $this->user('community');
        $secondReserver = $this->user('community');
        [$secondEvent] = $this->eligibleContext($secondVendor, '4.00');
        $secondItem = $this->item($secondVendor);

        $this->mock(
            ItemReservationReferenceGenerator::class,
            function (MockInterface $mock) use ($existingReference) {
                $mock->shouldReceive('generate')->times(5)->andReturn($existingReference);
            },
        );

        Sanctum::actingAs($secondReserver);
        $this->postJson('/api/reservations', ['vendor_item_id' => $secondItem->id])
            ->assertConflict()
            ->assertJsonPath('error', 'reservation_reference_generation_failed');
        $this->assertDatabaseCountForEvent($secondEvent, 0);
        $this->assertSame(0, ItemReservationAudit::query()
            ->whereHas('itemReservation', fn ($query) => $query
                ->where('carboot_event_id', $secondEvent->id))
            ->count());
    }

    public function test_unrelated_database_errors_are_rethrown(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '3.00');
        $item = $this->item($vendor);

        $this->mock(
            ItemReservationReferenceGenerator::class,
            function (MockInterface $mock) {
                $mock->shouldReceive('generate')->once()->andReturn(str_repeat('X', 40));
            },
        );

        $this->expectException(QueryException::class);
        $this->app->make(ItemReservationService::class)->create($reserver, $item->id);
    }

    public function test_community_and_vendor_reads_are_scoped_and_private(): void
    {
        $vendor = $this->user('community');
        $otherVendor = $this->user('community');
        $reserver = $this->user('community', [
            'phone_number' => '0123456789',
        ]);
        $otherUser = $this->user('community');
        $this->eligibleContext($vendor, '8.00');
        $item = $this->item($vendor);

        Sanctum::actingAs($reserver);
        $created = $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertCreated();
        $reservation = $this->trackReservationByReference(
            $created->json('reservation.public_reference'),
        );

        $this->getJson('/api/reservations/me')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.id');
        $this->getJson("/api/reservations/{$reservation->public_reference}")
            ->assertOk()
            ->assertJsonMissing(['email' => $vendor->email])
            ->assertJsonMissing(['phone_number' => $vendor->phone_number]);
        $this->getJson("/api/reservations/{$reservation->id}")->assertNotFound();

        Sanctum::actingAs($otherUser);
        $this->getJson('/api/reservations/me')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/reservations/{$reservation->public_reference}")->assertNotFound();
        $this->postJson("/api/reservations/{$reservation->public_reference}/cancel")
            ->assertNotFound();

        Sanctum::actingAs($vendor);
        $this->getJson('/api/vendor/item-reservations')
            ->assertOk()
            ->assertJsonPath('data.0.reserving_user.name', $reserver->name)
            ->assertJsonMissing(['email' => $reserver->email])
            ->assertJsonMissing(['phone_number' => '0123456789']);
        $this->getJson("/api/vendor/item-reservations/{$reservation->public_reference}")
            ->assertOk();

        Sanctum::actingAs($otherVendor);
        $this->getJson('/api/vendor/item-reservations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/vendor/item-reservations/{$reservation->public_reference}")
            ->assertNotFound();
    }

    public function test_reserving_user_cancellation_is_atomic_and_restores_reservability(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '7.00');
        $item = $this->item($vendor);

        Sanctum::actingAs($reserver);
        $created = $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertCreated();
        $reservation = $this->trackReservationByReference(
            $created->json('reservation.public_reference'),
        );

        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('item.has_active_reservation', true)
            ->assertJsonPath('item.is_reservable', false);

        $this->postJson("/api/reservations/{$reservation->public_reference}/cancel", [
            'reason' => 'Changed my plans',
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'cancelled')
            ->assertJsonPath('reservation.charge_status', 'cancelled');

        $cancelled = $reservation->fresh();
        $this->assertNull($cancelled->active_lock);
        $this->assertSame($reserver->id, $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(2, $cancelled->audits()->count());

        $this->postJson("/api/reservations/{$reservation->public_reference}/cancel")
            ->assertConflict()
            ->assertJsonPath('error', 'reservation_not_pending');
        $this->assertSame(2, $cancelled->audits()->count());

        $this->getJson("/api/marketplace/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('item.has_active_reservation', false)
            ->assertJsonPath('item.is_reservable', true);
    }

    public function test_vendor_can_cancel_owned_pending_but_not_another_vendors_reservation(): void
    {
        $vendor = $this->user('community');
        $otherVendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '6.00');
        $item = $this->item($vendor);

        Sanctum::actingAs($reserver);
        $created = $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertCreated();
        $reservation = $this->trackReservationByReference(
            $created->json('reservation.public_reference'),
        );

        Sanctum::actingAs($otherVendor);
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/cancel")
            ->assertNotFound();

        Sanctum::actingAs($vendor);
        $this->postJson("/api/vendor/item-reservations/{$reservation->public_reference}/cancel", [
            'reason' => 'Item unavailable',
        ])->assertOk()
            ->assertJsonPath('reservation.reservation_status', 'cancelled');
        $this->assertSame($vendor->id, $reservation->fresh()->cancelled_by);
    }

    public function test_item_deletion_is_blocked_after_active_or_cancelled_history(): void
    {
        Storage::fake('public');
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '2.00');
        $item = $this->item($vendor);
        Storage::disk('public')->put('reuse-items/phase42-blocked.jpg', 'image');
        $item->update(['image_path' => 'reuse-items/phase42-blocked.jpg']);

        Sanctum::actingAs($reserver);
        $created = $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertCreated();
        $reservation = $this->trackReservationByReference(
            $created->json('reservation.public_reference'),
        );

        Sanctum::actingAs($vendor);
        $this->deleteJson("/api/vendor/items/{$item->id}")
            ->assertConflict()
            ->assertJsonPath('error', 'item_has_reservation_history');
        $this->assertDatabaseHas('vendor_items', ['id' => $item->id]);
        Storage::disk('public')->assertExists('reuse-items/phase42-blocked.jpg');

        Sanctum::actingAs($reserver);
        $this->postJson("/api/reservations/{$reservation->public_reference}/cancel")
            ->assertOk();

        Sanctum::actingAs($vendor);
        $this->deleteJson("/api/vendor/items/{$item->id}")
            ->assertConflict()
            ->assertJsonPath('error', 'item_has_reservation_history');
        $this->assertDatabaseHas('item_reservations', ['id' => $reservation->id]);

        $deletable = $this->item($vendor, 'No Reservation History');
        $this->deleteJson("/api/vendor/items/{$deletable->id}")->assertOk();
        $this->itemIds = array_values(array_diff($this->itemIds, [$deletable->id]));
    }

    public function test_audits_are_append_only_through_the_model_and_survive_cancellation(): void
    {
        $vendor = $this->user('community');
        $reserver = $this->user('community');
        $this->eligibleContext($vendor, '9.00');
        $item = $this->item($vendor);

        Sanctum::actingAs($reserver);
        $created = $this->postJson('/api/reservations', ['vendor_item_id' => $item->id])
            ->assertCreated();
        $reservation = $this->trackReservationByReference(
            $created->json('reservation.public_reference'),
        );
        $audit = $reservation->audits()->firstOrFail();

        try {
            $audit->update(['note' => 'tampered']);
            $this->fail('Append-only audit update was accepted.');
        } catch (LogicException $exception) {
            $this->assertSame('Item reservation audits are append-only.', $exception->getMessage());
        }

        try {
            $audit->delete();
            $this->fail('Append-only audit delete was accepted.');
        } catch (LogicException $exception) {
            $this->assertSame('Item reservation audits are append-only.', $exception->getMessage());
        }

        $this->postJson("/api/reservations/{$reservation->public_reference}/cancel")
            ->assertOk();
        $this->assertDatabaseHas('item_reservations', ['id' => $reservation->id]);
        $this->assertSame(2, ItemReservationAudit::query()
            ->where('item_reservation_id', $reservation->id)->count());
    }

    public function test_marketplace_fee_readiness_and_active_lookup_do_not_grow_per_item_queries(): void
    {
        $configuredVendor = $this->user('community');
        $unconfiguredVendor = $this->user('community');
        $this->eligibleContext($configuredVendor, '0.00');
        $this->eligibleContext($unconfiguredVendor, null);
        $configuredItem = $this->item($configuredVendor, 'Configured Marketplace Item');
        $unconfiguredItem = $this->item($unconfiguredVendor, 'Unconfigured Marketplace Item');

        $response = $this->getJson('/api/marketplace/items?per_page=48')->assertOk();
        $configured = collect($response->json('data'))->firstWhere('id', $configuredItem->id);
        $unconfigured = collect($response->json('data'))->firstWhere('id', $unconfiguredItem->id);
        $this->assertTrue($configured['is_reservable']);
        $this->assertFalse($configured['has_active_reservation']);
        $this->assertFalse($unconfigured['is_reservable']);
        $this->assertFalse($unconfigured['has_active_reservation']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/marketplace/items?per_page=48')->assertOk();
        $twoItemQueryCount = count(DB::getQueryLog());

        $this->item($configuredVendor, 'Additional Marketplace Item A');
        $this->item($configuredVendor, 'Additional Marketplace Item B');
        DB::flushQueryLog();
        $this->getJson('/api/marketplace/items?per_page=48')->assertOk();
        $fourItemQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($twoItemQueryCount, $fourItemQueryCount);
    }

    private function user(string $role, array $overrides = []): User
    {
        $user = User::query()->create([
            'name' => 'Phase42 User '.uniqid(),
            'email' => 'phase42-'.uniqid().'@example.test',
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
            'title' => 'Phase42 Event '.uniqid(),
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(6),
            'status' => 'Available',
            'description' => 'Phase 4.2 reservation event',
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
            'product_details' => 'Phase 4.2 eligible booking',
            'approval_status' => 'Approved',
        ]);
        $this->bookingIds[] = $booking->id;

        return [$event, $booking];
    }

    private function item(
        User $vendor,
        string $name = 'Phase42 Item',
        string $status = 'active',
    ): VendorItem {
        $item = VendorItem::query()->create([
            'user_id' => $vendor->id,
            'name' => $name,
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => '25.00',
            'description' => 'Phase 4.2 reservation item',
            'status' => $status,
        ]);
        $this->itemIds[] = $item->id;

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

    /**
     * @return array<string, mixed>
     */
    private function reservationRow(
        VendorItem $item,
        User $reserver,
        User $vendor,
        CarbootEvent $event,
        Booking $booking,
        string $reference,
    ): array {
        return [
            'public_reference' => $reference,
            'vendor_item_id' => $item->id,
            'reserving_user_id' => $reserver->id,
            'vendor_user_id' => $vendor->id,
            'carboot_event_id' => $event->id,
            'vendor_booking_id' => $booking->id,
            'reservation_status' => 'pending_charge',
            'active_lock' => 1,
            'service_fee_amount' => '5.00',
            'service_fee_currency' => 'MYR',
            'charge_status' => 'required',
            'item_name_snapshot' => $item->name,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function assertDatabaseCountForEvent(CarbootEvent $event, int $expected): void
    {
        $this->assertSame(
            $expected,
            ItemReservation::query()->where('carboot_event_id', $event->id)->count(),
        );
    }
}
