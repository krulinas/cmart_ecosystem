<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase41EventReservationFeeTest extends TestCase
{
    /** @var list<int> */
    private array $createdUserIds = [];

    /** @var list<int> */
    private array $createdEventIds = [];

    protected function tearDown(): void
    {
        CarbootEvent::query()->whereIn('id', $this->createdEventIds)->get()->each->delete();
        User::query()->whereIn('id', $this->createdUserIds)->delete();

        parent::tearDown();
    }

    public function test_fee_schema_is_nullable_decimal_with_non_negative_check(): void
    {
        $this->assertTrue(Schema::hasColumn('carboot_events', 'item_reservation_service_fee'));

        $column = DB::selectOne(
            <<<'SQL'
                SELECT DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE, IS_NULLABLE, COLUMN_DEFAULT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'carboot_events'
                  AND COLUMN_NAME = 'item_reservation_service_fee'
            SQL,
        );

        $this->assertSame('decimal', $column->DATA_TYPE);
        $this->assertSame(10, (int) $column->NUMERIC_PRECISION);
        $this->assertSame(2, (int) $column->NUMERIC_SCALE);
        $this->assertSame('YES', $column->IS_NULLABLE);
        // MariaDB 10.4 reports an SQL NULL default as the string "NULL".
        $this->assertContains($column->COLUMN_DEFAULT, [null, 'NULL']);

        $check = DB::selectOne(
            <<<'SQL'
                SELECT COUNT(*) AS aggregate
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'carboot_events'
                  AND CONSTRAINT_NAME = 'carboot_events_item_reservation_fee_non_negative'
                  AND CONSTRAINT_TYPE = 'CHECK'
            SQL,
        );
        $this->assertSame(1, (int) $check->aggregate);
    }

    public function test_organizer_can_create_and_update_null_zero_and_positive_fees(): void
    {
        Sanctum::actingAs($this->createUser('organizer'));

        $create = $this->postJson('/api/carboot-events', $this->eventPayload([
            'item_reservation_service_fee' => null,
        ]));

        $create->assertCreated()
            ->assertJsonPath('event.item_reservation_service_fee', null);

        $eventId = (int) $create->json('event.id');
        $this->createdEventIds[] = $eventId;

        $this->assertDatabaseHas('carboot_events', [
            'id' => $eventId,
            'item_reservation_service_fee' => null,
        ]);

        $this->putJson("/api/carboot-events/{$eventId}", [
            'item_reservation_service_fee' => '0.00',
        ])->assertOk()
            ->assertJsonPath('event.item_reservation_service_fee', '0.00');

        $this->putJson("/api/carboot-events/{$eventId}", [
            'item_reservation_service_fee' => '25.50',
        ])->assertOk()
            ->assertJsonPath('event.item_reservation_service_fee', '25.50');

        $this->assertSame(
            '25.50',
            CarbootEvent::query()->findOrFail($eventId)->item_reservation_service_fee,
        );
    }

    public function test_fee_validation_rejects_negative_precision_and_range_errors(): void
    {
        $event = $this->createEvent();
        Sanctum::actingAs($this->createUser('organizer'));

        foreach (['-0.01', '1.001', '100000000.00', 'RM5', 'free'] as $invalid) {
            $this->putJson("/api/carboot-events/{$event->id}", [
                'item_reservation_service_fee' => $invalid,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('item_reservation_service_fee');
        }

        $this->assertNull($event->fresh()->item_reservation_service_fee);
    }

    public function test_community_and_cmart_management_cannot_configure_fee(): void
    {
        $event = $this->createEvent();

        foreach (['community', 'cmart_management'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->putJson("/api/carboot-events/{$event->id}", [
                'item_reservation_service_fee' => '5.00',
            ])->assertForbidden();
        }

        $this->assertNull($event->fresh()->item_reservation_service_fee);
    }

    public function test_public_event_payload_hides_fee_and_update_is_booking_financially_isolated(): void
    {
        $event = $this->createEvent(['item_reservation_service_fee' => '8.50']);

        $bookingsBefore = Booking::query()
            ->orderBy('id')
            ->get(['id', 'approval_status'])
            ->toArray();
        $invoicesBefore = Invoice::query()
            ->orderBy('id')
            ->get(['id', 'booking_id', 'amount', 'payment_status', 'payment_proof_path', 'payment_submitted_at'])
            ->toArray();
        $allocationsBefore = BookingDayAllocation::query()
            ->orderBy('id')
            ->get(['id', 'booking_id', 'allocation_status', 'active_lock'])
            ->toArray();

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonMissingPath('item_reservation_service_fee');

        Sanctum::actingAs($this->createUser('organizer'));
        $this->putJson("/api/carboot-events/{$event->id}", [
            'item_reservation_service_fee' => '12.00',
        ])->assertOk()
            ->assertJsonPath('event.item_reservation_service_fee', '12.00');

        $this->assertSame(
            $bookingsBefore,
            Booking::query()->orderBy('id')->get(['id', 'approval_status'])->toArray(),
        );
        $this->assertSame(
            $invoicesBefore,
            Invoice::query()
                ->orderBy('id')
                ->get(['id', 'booking_id', 'amount', 'payment_status', 'payment_proof_path', 'payment_submitted_at'])
                ->toArray(),
        );
        $this->assertSame(
            $allocationsBefore,
            BookingDayAllocation::query()
                ->orderBy('id')
                ->get(['id', 'booking_id', 'allocation_status', 'active_lock'])
                ->toArray(),
        );
    }

    private function createUser(string $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'vendor_status' => 'none',
        ]);
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function createEvent(array $overrides = []): CarbootEvent
    {
        $event = CarbootEvent::query()->create(array_merge(
            $this->eventPayload(),
            $overrides,
        ));
        $this->createdEventIds[] = $event->id;

        return $event;
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Phase 4.1 Fee Event '.uniqid(),
            'starts_at' => now()->addDays(20)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(20)->addHours(8)->format('Y-m-d H:i:s'),
            'status' => 'Available',
            'description' => 'Phase 4.1 event fee fixture',
            'max_slots' => 20,
        ], $overrides);
    }
}
