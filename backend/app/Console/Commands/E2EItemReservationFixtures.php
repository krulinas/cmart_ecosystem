<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\Invoice;
use App\Models\ItemReservation;
use App\Models\ReuseItemImage;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorBusinessProfile;
use App\Models\VendorCategory;
use App\Models\VendorItem;
use App\Services\ItemReservationReferenceGenerator;
use App\Support\E2EDatabaseGuard;
use App\Support\TestingDatabaseGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Phase 4.5 — deterministic item-reservation browser fixtures.
 */
class E2EItemReservationFixtures extends Command
{
    protected $signature = 'e2e:item-reservation-fixtures
                            {action=create : create, status, or cleanup}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Create or remove isolated Phase 4.5 item-reservation E2E fixtures';

    public const MARKER = 'E2E-P45';

    public const EVENT_TITLE = self::MARKER.' Item Reservation Weekend';

    public const ZERO_FEE_EVENT_TITLE = self::MARKER.' Zero Fee Weekend';

    public const VENDOR_EMAIL = 'e2e-p45-vendor@example.test';

    public const UNRELATED_VENDOR_EMAIL = 'e2e-p45-vendor-b@example.test';

    public const RESERVER_EMAIL = 'e2e-p45-reserver@example.test';

    public const COMPETITOR_EMAIL = 'e2e-p45-competitor@example.test';

    public const ORGANIZER_EMAIL = 'e2e-p45-organizer@example.test';

    public const MANAGEMENT_EMAIL = 'e2e-p45-management@example.test';

    public const PASSWORD = 'P45-E2E-password';

    private const SPACE_NAME = Space::PHYSICAL_PARKING_SITE;

    private const SERVICE_FEE = '15.00';

    public function handle(): int
    {
        try {
            $this->assertSafeDatabase();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'create' => $this->createFixtures(),
            'status' => $this->status(),
            'cleanup' => $this->cleanupFixtures(),
            default => $this->failUnknownAction(),
        };
    }

    private function assertSafeDatabase(): void
    {
        $app = $this->laravel;
        $config = $app->make('config');
        $connection = (string) $config->get('database.default', '');
        $database = (string) $config->get("database.connections.{$connection}.database", '');

        if ($app->environment('e2e')) {
            E2EDatabaseGuard::assertSafeFromApplication($app);

            return;
        }

        if ($app->environment('testing') || $app->runningUnitTests()) {
            TestingDatabaseGuard::assertSafeFromApplication($app);

            return;
        }

        throw new \RuntimeException(
            'Unsafe fixture environment: e2e:item-reservation-fixtures may only run under APP_ENV=e2e or testing. '.
            "Resolved APP_ENV={$app->environment()}, DB={$database}. No database operation was performed.",
        );
    }

    private function createFixtures(): int
    {
        $this->purge();

        try {
            $payload = DB::transaction(function () {
                $thrift = VendorCategory::query()->where('slug', 'pre-loved-thrift')->firstOrFail();
                $space = Space::defaultPhysical();

                $vendor = $this->createUser(self::VENDOR_EMAIL, 'Phase 4.5 E2E Vendor', 'community', 'approved');
                $unrelatedVendor = $this->createUser(
                    self::UNRELATED_VENDOR_EMAIL,
                    'Phase 4.5 E2E Unrelated Vendor',
                    'community',
                    'approved',
                );
                $reserver = $this->createUser(
                    self::RESERVER_EMAIL,
                    'Phase 4.5 E2E Reserver',
                    'community',
                    'none',
                );
                $competitor = $this->createUser(
                    self::COMPETITOR_EMAIL,
                    'Phase 4.5 E2E Competitor',
                    'community',
                    'none',
                );
                $organizer = $this->createUser(
                    self::ORGANIZER_EMAIL,
                    'Phase 4.5 E2E Organizer',
                    'organizer',
                    'none',
                );
                $management = $this->createUser(
                    self::MANAGEMENT_EMAIL,
                    'Phase 4.5 E2E CMart Management',
                    'cmart_management',
                    'none',
                );

                VendorBusinessProfile::query()->create([
                    'user_id' => $vendor->id,
                    'business_name' => self::MARKER.' Camera Booth',
                    'business_phone' => '0123456789',
                    'business_category' => $thrift->label,
                    'vendor_category_id' => $thrift->id,
                    'description' => self::MARKER.' vendor profile',
                ]);

                $starts = now()->addDays(14)->startOfDay()->setTime(8, 0);
                $event = CarbootEvent::query()->create([
                    'title' => self::EVENT_TITLE,
                    'description' => self::MARKER.' isolated browser fixture',
                    'starts_at' => $starts,
                    'ends_at' => $starts->copy()->addHours(8),
                    'status' => 'Available',
                    'max_slots' => 20,
                    'item_reservation_service_fee' => self::SERVICE_FEE,
                ]);

                $booking = Booking::query()->create([
                    'user_id' => $vendor->id,
                    'space_id' => $space->id,
                    'carboot_event_id' => $event->id,
                    'booking_date' => $event->starts_at->toDateString(),
                    'vendor_category_id' => $thrift->id,
                    'category_label_snapshot' => $thrift->label,
                    'product_category' => $thrift->label,
                    'product_details' => self::MARKER.' approved vendor booking',
                    'approval_status' => 'Approved',
                ]);

                $successItem = $this->createItem($vendor, $thrift, self::MARKER.' Success Camera');
                $conflictItem = $this->createItem($vendor, $thrift, self::MARKER.' Conflict Lamp');
                $cancelItem = $this->createItem($vendor, $thrift, self::MARKER.' Cancel Radio');
                $expiryItem = $this->createItem($vendor, $thrift, self::MARKER.' Expiry Clock');
                $completionItem = $this->createItem($vendor, $thrift, self::MARKER.' Complete Bag');
                $accessItem = $this->createItem($vendor, $thrift, self::MARKER.' Access Mirror');
                $ownerOnlyItem = $this->createItem($vendor, $thrift, self::MARKER.' Owner Only Hat');

                $heldReservation = $this->seedReservation(
                    $conflictItem,
                    $competitor,
                    $vendor,
                    $event,
                    $booking,
                    ItemReservation::STATUS_PENDING_CHARGE,
                    ItemReservation::CHARGE_REQUIRED,
                    self::SERVICE_FEE,
                );

                $zeroStarts = now()->addDays(21)->startOfDay()->setTime(9, 0);
                $zeroFeeEvent = CarbootEvent::query()->create([
                    'title' => self::ZERO_FEE_EVENT_TITLE,
                    'description' => self::MARKER.' zero-fee fixture',
                    'starts_at' => $zeroStarts,
                    'ends_at' => $zeroStarts->copy()->addHours(6),
                    'status' => 'Available',
                    'max_slots' => 10,
                    'item_reservation_service_fee' => '0.00',
                ]);
                $zeroFeeBooking = Booking::query()->create([
                    'user_id' => $vendor->id,
                    'space_id' => $space->id,
                    'carboot_event_id' => $zeroFeeEvent->id,
                    'booking_date' => $zeroFeeEvent->starts_at->toDateString(),
                    'vendor_category_id' => $thrift->id,
                    'category_label_snapshot' => $thrift->label,
                    'product_category' => $thrift->label,
                    'product_details' => self::MARKER.' zero-fee booking',
                    'approval_status' => 'Approved',
                ]);
                $zeroFeeItem = $this->createItem($vendor, $thrift, self::MARKER.' Zero Fee Mug');

                return [
                    'database' => DB::connection()->getDatabaseName(),
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'service_fee_amount' => self::SERVICE_FEE,
                    'service_fee_currency' => 'MYR',
                    'zero_fee_event_id' => $zeroFeeEvent->id,
                    'zero_fee_event_title' => $zeroFeeEvent->title,
                    'vendor_email' => self::VENDOR_EMAIL,
                    'vendor_password' => self::PASSWORD,
                    'unrelated_vendor_email' => self::UNRELATED_VENDOR_EMAIL,
                    'unrelated_vendor_password' => self::PASSWORD,
                    'reserver_email' => self::RESERVER_EMAIL,
                    'reserver_password' => self::PASSWORD,
                    'competitor_email' => self::COMPETITOR_EMAIL,
                    'competitor_password' => self::PASSWORD,
                    'organizer_email' => self::ORGANIZER_EMAIL,
                    'organizer_password' => self::PASSWORD,
                    'cmart_management_email' => self::MANAGEMENT_EMAIL,
                    'cmart_management_password' => self::PASSWORD,
                    'vendor_booking_id' => $booking->id,
                    'zero_fee_booking_id' => $zeroFeeBooking->id,
                    'space_id' => $space->id,
                    'success_item_id' => $successItem->id,
                    'success_item_name' => $successItem->name,
                    'conflict_item_id' => $conflictItem->id,
                    'conflict_item_name' => $conflictItem->name,
                    'held_reservation_reference' => $heldReservation->public_reference,
                    'cancel_item_id' => $cancelItem->id,
                    'cancel_item_name' => $cancelItem->name,
                    'expiry_item_id' => $expiryItem->id,
                    'expiry_item_name' => $expiryItem->name,
                    'completion_item_id' => $completionItem->id,
                    'completion_item_name' => $completionItem->name,
                    'access_item_id' => $accessItem->id,
                    'access_item_name' => $accessItem->name,
                    'owner_only_item_id' => $ownerOnlyItem->id,
                    'owner_only_item_name' => $ownerOnlyItem->name,
                    'zero_fee_item_id' => $zeroFeeItem->id,
                    'zero_fee_item_name' => $zeroFeeItem->name,
                    'isolation' => [
                        'fixture_invoice_count' => Invoice::query()->where('booking_id', $booking->id)->count(),
                        'fixture_allocation_count' => BookingDayAllocation::query()
                            ->where('booking_id', $booking->id)
                            ->count(),
                        'fixture_booking_audit_count' => BookingAuditLog::query()
                            ->where('booking_id', $booking->id)
                            ->count(),
                    ],
                ];
            });

            $this->attachFixtureImages([
                $payload['success_item_id'],
                $payload['conflict_item_id'],
                $payload['cancel_item_id'],
                $payload['expiry_item_id'],
                $payload['completion_item_id'],
                $payload['access_item_id'],
                $payload['owner_only_item_id'],
                $payload['zero_fee_item_id'],
            ]);
        } catch (Throwable $exception) {
            $this->purge();
            throw $exception;
        }

        return $this->emit($payload);
    }

    private function status(): int
    {
        return $this->emit($this->residue());
    }

    private function cleanupFixtures(): int
    {
        return $this->emit($this->purge());
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function attachFixtureImages(array $itemIds): void
    {
        foreach ($itemIds as $itemId) {
            $path = 'reuse-items/e2e-p45-item-'.$itemId.'.jpg';
            Storage::disk('public')->put($path, $this->tinyJpegBytes());

            ReuseItemImage::query()->create([
                'vendor_item_id' => $itemId,
                'image_path' => $path,
                'sort_order' => 0,
                'is_primary' => true,
            ]);

            VendorItem::query()->whereKey($itemId)->update([
                'image_path' => $path,
            ]);
        }
    }

    private function tinyJpegBytes(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEABj8Cf//EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAT8hf//Z',
        );
    }

    private function createUser(string $email, string $name, string $role, string $vendorStatus): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'role' => $role,
            'vendor_status' => $vendorStatus,
        ]);
    }

    private function createItem(User $vendor, VendorCategory $category, string $name): VendorItem
    {
        return VendorItem::query()->create([
            'user_id' => $vendor->id,
            'name' => $name,
            'vendor_category_id' => $category->id,
            'category' => $category->label,
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => '35.00',
            'description' => self::MARKER.' reservable item',
            'status' => 'active',
        ]);
    }

    private function seedReservation(
        VendorItem $item,
        User $reserver,
        User $vendor,
        CarbootEvent $event,
        Booking $booking,
        string $reservationStatus,
        string $chargeStatus,
        string $feeAmount,
    ): ItemReservation {
        $reference = app(ItemReservationReferenceGenerator::class)->generate();

        return ItemReservation::query()->create([
            'public_reference' => $reference,
            'vendor_item_id' => $item->id,
            'reserving_user_id' => $reserver->id,
            'vendor_user_id' => $vendor->id,
            'carboot_event_id' => $event->id,
            'vendor_booking_id' => $booking->id,
            'reservation_status' => $reservationStatus,
            'active_lock' => ItemReservation::activeLockForStatus($reservationStatus),
            'service_fee_amount' => $feeAmount,
            'service_fee_currency' => 'MYR',
            'charge_status' => $chargeStatus,
            'item_name_snapshot' => $item->name,
        ]);
    }

    /**
     * @return array<string, int|string|array<string, int>>
     */
    private function purge(): array
    {
        return DB::transaction(function () {
            $eventIds = CarbootEvent::query()->where('title', 'like', self::MARKER.'%')->pluck('id');
            $userIds = User::query()->where('email', 'like', 'e2e-p45-%@example.test')->pluck('id');
            $itemIds = VendorItem::query()
                ->where('name', 'like', self::MARKER.'%')
                ->orWhereIn('user_id', $userIds)
                ->pluck('id');
            $reservationIds = ItemReservation::query()
                ->whereIn('vendor_item_id', $itemIds)
                ->orWhere('item_name_snapshot', 'like', self::MARKER.'%')
                ->pluck('id');
            $bookingIds = Booking::query()
                ->whereIn('carboot_event_id', $eventIds)
                ->orWhereIn('user_id', $userIds)
                ->pluck('id');

            $deletedAudits = DB::table('item_reservation_audits')
                ->whereIn('item_reservation_id', $reservationIds)
                ->delete();
            $deletedReservations = DB::table('item_reservations')
                ->whereIn('id', $reservationIds)
                ->delete();

            $imagePaths = ReuseItemImage::query()
                ->whereIn('vendor_item_id', $itemIds)
                ->pluck('image_path')
                ->merge(
                    VendorItem::query()->whereIn('id', $itemIds)->pluck('image_path'),
                )
                ->filter()
                ->unique()
                ->values();

            $deletedItems = 0;
            VendorItem::query()->whereIn('id', $itemIds)->get()->each(function (VendorItem $item) use (&$deletedItems) {
                $item->delete();
                $deletedItems++;
            });

            foreach ($imagePaths as $path) {
                if (is_string($path) && str_contains($path, 'e2e-p45')) {
                    Storage::disk('public')->delete($path);
                }
            }

            $deleted = [
                'database' => DB::connection()->getDatabaseName(),
                'item_reservation_audits' => $deletedAudits,
                'item_reservations' => $deletedReservations,
                'vendor_items' => $deletedItems,
                'booking_day_allocations' => BookingDayAllocation::query()->whereIn('booking_id', $bookingIds)->delete(),
                'invoices' => Invoice::query()->whereIn('booking_id', $bookingIds)->delete(),
                'booking_audit_logs' => BookingAuditLog::query()->whereIn('booking_id', $bookingIds)->delete(),
                'bookings' => Booking::query()->whereIn('id', $bookingIds)->delete(),
                'carboot_events' => CarbootEvent::query()->whereIn('id', $eventIds)->delete(),
                'vendor_business_profiles' => VendorBusinessProfile::query()->whereIn('user_id', $userIds)->delete(),
            ];

            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();

            $deleted['users'] = User::query()->whereIn('id', $userIds)->delete();
            $deleted['spaces'] = 0;
            $deleted['residue'] = $this->residueCounters();

            return $deleted;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function residue(): array
    {
        return [
            'database' => DB::connection()->getDatabaseName(),
            ...$this->residueCounters(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function residueCounters(): array
    {
        $eventIds = CarbootEvent::query()->where('title', 'like', self::MARKER.'%')->pluck('id');
        $userIds = User::query()->where('email', 'like', 'e2e-p45-%@example.test')->pluck('id');
        $itemIds = VendorItem::query()->where('name', 'like', self::MARKER.'%')->pluck('id');
        $reservationIds = ItemReservation::query()
            ->whereIn('vendor_item_id', $itemIds)
            ->orWhere('item_name_snapshot', 'like', self::MARKER.'%')
            ->pluck('id');

        return [
            'users' => $userIds->count(),
            'events' => $eventIds->count(),
            'items' => $itemIds->count(),
            'reservations' => $reservationIds->count(),
            'audits' => DB::table('item_reservation_audits')
                ->whereIn('item_reservation_id', $reservationIds)
                ->count(),
            'orphan_audits' => DB::table('item_reservation_audits')
                ->leftJoin(
                    'item_reservations',
                    'item_reservations.id',
                    '=',
                    'item_reservation_audits.item_reservation_id',
                )
                ->whereNull('item_reservations.id')
                ->where('item_reservation_audits.note', 'like', '%'.self::MARKER.'%')
                ->count(),
            'active_locks' => ItemReservation::query()
                ->whereIn('id', $reservationIds)
                ->where('active_lock', 1)
                ->count(),
            'bookings' => Booking::query()
                ->whereIn('carboot_event_id', $eventIds)
                ->orWhereIn('user_id', $userIds)
                ->count(),
            'spaces' => Space::query()->where('space_size', self::SPACE_NAME)->count(),
            'fixture_images' => collect(Storage::disk('public')->files('reuse-items'))
                ->filter(fn (string $path) => str_contains($path, 'e2e-p45'))
                ->count(),
        ];
    }

    private function emit(array $payload): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Field', 'Value'],
                collect($payload)->map(
                    fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : $value],
                )->values()->all(),
            );
        }

        return self::SUCCESS;
    }

    private function failUnknownAction(): int
    {
        $this->error('Unknown action. Use create, status, or cleanup.');

        return self::FAILURE;
    }
}
