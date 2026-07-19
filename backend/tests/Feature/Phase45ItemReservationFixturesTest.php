<?php

namespace Tests\Feature;

use App\Console\Commands\E2EItemReservationFixtures;
use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\ItemReservation;
use App\Models\ReuseItemImage;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase45ItemReservationFixturesTest extends TestCase
{
    protected function tearDown(): void
    {
        Artisan::call('e2e:item-reservation-fixtures', ['action' => 'cleanup', '--json' => true]);

        parent::tearDown();
    }

    public function test_create_status_and_cleanup_json_contracts_and_marketplace_eligibility(): void
    {
        $create = Artisan::call('e2e:item-reservation-fixtures', [
            'action' => 'create',
            '--json' => true,
        ]);
        $this->assertSame(0, $create);

        $payload = $this->decodeJsonOutput(Artisan::output());
        $this->assertSame('cmart_test', $payload['database']);
        $this->assertSame(E2EItemReservationFixtures::VENDOR_EMAIL, $payload['vendor_email']);
        $this->assertSame(E2EItemReservationFixtures::PASSWORD, $payload['vendor_password']);
        $this->assertSame(E2EItemReservationFixtures::RESERVER_EMAIL, $payload['reserver_email']);
        $this->assertSame(E2EItemReservationFixtures::ORGANIZER_EMAIL, $payload['organizer_email']);
        $this->assertSame('15.00', $payload['service_fee_amount']);
        $this->assertArrayHasKey('success_item_id', $payload);
        $this->assertArrayHasKey('held_reservation_reference', $payload);
        $this->assertArrayHasKey('isolation', $payload);

        $this->assertDatabaseHas('users', ['email' => E2EItemReservationFixtures::VENDOR_EMAIL]);
        $this->assertDatabaseHas('carboot_events', ['title' => E2EItemReservationFixtures::EVENT_TITLE]);
        $this->assertDatabaseHas('item_reservations', [
            'public_reference' => $payload['held_reservation_reference'],
            'active_lock' => 1,
        ]);

        $this->getJson('/api/marketplace/items/'.$payload['success_item_id'])
            ->assertOk()
            ->assertJsonPath('item.is_reservable', true)
            ->assertJsonPath('item.has_active_reservation', false);

        $this->getJson('/api/marketplace/items/'.$payload['conflict_item_id'])
            ->assertOk()
            ->assertJsonPath('item.is_reservable', false)
            ->assertJsonPath('item.has_active_reservation', true);

        $imagePath = VendorItem::query()->findOrFail($payload['success_item_id'])->image_path;
        $this->assertNotEmpty($imagePath);
        Storage::disk('public')->assertExists($imagePath);

        $statusCode = Artisan::call('e2e:item-reservation-fixtures', [
            'action' => 'status',
            '--json' => true,
        ]);
        $this->assertSame(0, $statusCode);
        $status = $this->decodeJsonOutput(Artisan::output());
        $this->assertSame('cmart_test', $status['database']);
        $this->assertGreaterThanOrEqual(6, $status['users']);
        $this->assertGreaterThanOrEqual(1, $status['events']);
        $this->assertGreaterThanOrEqual(1, $status['reservations']);
        $this->assertGreaterThanOrEqual(1, $status['active_locks']);

        $cleanupCode = Artisan::call('e2e:item-reservation-fixtures', [
            'action' => 'cleanup',
            '--json' => true,
        ]);
        $this->assertSame(0, $cleanupCode);
        $cleanup = $this->decodeJsonOutput(Artisan::output());
        $this->assertSame(0, $cleanup['residue']['users']);
        $this->assertSame(0, $cleanup['residue']['events']);
        $this->assertSame(0, $cleanup['residue']['items']);
        $this->assertSame(0, $cleanup['residue']['reservations']);
        $this->assertSame(0, $cleanup['residue']['audits']);
        $this->assertSame(0, $cleanup['residue']['orphan_audits']);
        $this->assertSame(0, $cleanup['residue']['active_locks']);
        $this->assertSame(0, $cleanup['residue']['bookings']);
        $this->assertSame(0, $cleanup['residue']['spaces']);
        $this->assertSame(0, $cleanup['residue']['fixture_images']);

        Storage::disk('public')->assertMissing($imagePath);
        $this->assertSame(0, User::query()->where('email', 'like', 'e2e-p45-%@example.test')->count());
        $this->assertSame(0, CarbootEvent::query()->where('title', 'like', E2EItemReservationFixtures::MARKER.'%')->count());
        $this->assertSame(0, Space::query()->where('space_size', 'E2E P45 Item Reservation')->count());
        $this->assertSame(0, ReuseItemImage::query()->where('image_path', 'like', '%e2e-p45%')->count());
    }

    public function test_create_is_idempotent_and_cleans_reverse_fk_dependencies(): void
    {
        Artisan::call('e2e:item-reservation-fixtures', ['action' => 'create', '--json' => true]);
        $first = $this->decodeJsonOutput(Artisan::output());

        $reservation = ItemReservation::query()
            ->where('public_reference', $first['held_reservation_reference'])
            ->firstOrFail();

        DB::table('item_reservation_audits')->insert([
            'item_reservation_id' => $reservation->id,
            'actor_user_id' => $reservation->reserving_user_id,
            'action' => 'reservation_created',
            'from_reservation_status' => null,
            'to_reservation_status' => ItemReservation::STATUS_PENDING_CHARGE,
            'from_charge_status' => null,
            'to_charge_status' => ItemReservation::CHARGE_REQUIRED,
            'note' => E2EItemReservationFixtures::MARKER.' audit seed',
            'metadata' => null,
            'created_at' => now(),
        ]);

        Artisan::call('e2e:item-reservation-fixtures', ['action' => 'create', '--json' => true]);
        $second = $this->decodeJsonOutput(Artisan::output());

        $this->assertNotEquals($first['success_item_id'], $second['success_item_id']);
        $this->assertSame(1, User::query()->where('email', E2EItemReservationFixtures::VENDOR_EMAIL)->count());
        $this->assertSame(
            1,
            ItemReservation::query()->where('item_name_snapshot', 'like', E2EItemReservationFixtures::MARKER.'%')->where('active_lock', 1)->count(),
        );

        Artisan::call('e2e:item-reservation-fixtures', ['action' => 'cleanup', '--json' => true]);
        $cleanup = $this->decodeJsonOutput(Artisan::output());
        $this->assertSame(0, $cleanup['residue']['orphan_audits']);
        $this->assertSame(0, DB::table('item_reservation_audits')->where('note', 'like', '%'.E2EItemReservationFixtures::MARKER.'%')->count());
        $this->assertSame(0, Booking::query()->where('product_details', 'like', E2EItemReservationFixtures::MARKER.'%')->count());
    }

    public function test_community_reserver_can_create_on_success_item_after_fixture_create(): void
    {
        Artisan::call('e2e:item-reservation-fixtures', ['action' => 'create', '--json' => true]);
        $payload = $this->decodeJsonOutput(Artisan::output());

        $reserver = User::query()->where('email', E2EItemReservationFixtures::RESERVER_EMAIL)->firstOrFail();
        Sanctum::actingAs($reserver);

        $this->postJson('/api/reservations', [
            'vendor_item_id' => $payload['success_item_id'],
        ])->assertCreated()
            ->assertJsonPath('reservation.reservation_status', ItemReservation::STATUS_PENDING_CHARGE)
            ->assertJsonPath('reservation.service_fee_amount', '15.00');
    }

    public function test_command_refuses_unknown_action(): void
    {
        $code = Artisan::call('e2e:item-reservation-fixtures', [
            'action' => 'explode',
            '--json' => true,
        ]);

        $this->assertSame(1, $code);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonOutput(string $output): array
    {
        $line = collect(preg_split('/\r\n|\r|\n/', $output) ?: [])
            ->map(fn (string $value) => trim($value))
            ->first(fn (string $value) => str_starts_with($value, '{'));

        $this->assertNotNull($line, 'Expected JSON fixture output, got: '.$output);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
