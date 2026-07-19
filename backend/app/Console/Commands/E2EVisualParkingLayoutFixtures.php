<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventLayoutAuditLog;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Models\VendorBusinessProfile;
use App\Models\VendorCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic fixtures for the Visual Parking Layout Builder E2E journey.
 *
 * Creates an empty-layout event with active EventDays so the Organizer can
 * generate the standard 4×16 template in-browser.
 */
class E2EVisualParkingLayoutFixtures extends Command
{
    protected $signature = 'e2e:visual-parking-layout-fixtures
                            {action=create : create, status, or cleanup}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Create or remove Visual Parking Layout Builder E2E fixtures';

    public const MARKER = 'E2E-VPL';

    public const EVENT_TITLE = self::MARKER.' Standard Parking Event';

    public const ORGANIZER_EMAIL = 'e2e-vpl-organizer@example.test';

    public const VENDOR_EMAIL = 'e2e-vpl-vendor@example.test';

    public const PASSWORD = 'VPL-E2E-password';

    private const SPACE_NAME = 'E2E VPL Standard';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'create' => $this->createFixtures(),
            'status' => $this->status(),
            'cleanup' => $this->cleanupFixtures(),
            default => $this->failUnknownAction(),
        };
    }

    private function createFixtures(): int
    {
        $this->purge();

        return $this->emit(DB::transaction(function () {
            $food = VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
            $thrift = VendorCategory::query()->where('slug', 'pre-loved-thrift')->firstOrFail();
            $space = Space::create([
                'space_size' => self::SPACE_NAME,
                'price' => 30,
                'status' => 'Available',
            ]);

            $organizer = $this->createUser(
                self::ORGANIZER_EMAIL,
                'VPL Organizer',
                'organizer',
                'none',
            );
            $vendor = $this->createUser(
                self::VENDOR_EMAIL,
                'VPL Vendor',
                'community',
                'approved',
            );
            VendorBusinessProfile::create([
                'user_id' => $vendor->id,
                'business_name' => 'VPL Vendor Business',
                'business_phone' => '0123456789',
                'business_category' => $food->label,
                'vendor_category_id' => $food->id,
                'description' => 'Visual parking layout E2E vendor profile',
            ]);

            $starts = now()->addDays(18)->startOfDay()->setTime(8, 0);
            $event = CarbootEvent::create([
                'title' => self::EVENT_TITLE,
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->addDay()->setTime(17, 0),
                'status' => 'Available',
                'description' => self::MARKER.' empty layout for standard template generation',
                'max_slots' => 100,
                'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
            ]);

            EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $starts->toDateString(),
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->setTime(17, 0),
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => 1,
            ]);
            EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $starts->copy()->addDay()->toDateString(),
                'starts_at' => $starts->copy()->addDay()->setTime(8, 0),
                'ends_at' => $starts->copy()->addDay()->setTime(17, 0),
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => 2,
            ]);

            return [
                'ok' => true,
                'database' => (string) config('database.connections.'.config('database.default').'.database'),
                'marker' => self::MARKER,
                'event_id' => $event->id,
                'event_title' => $event->title,
                'organizer_email' => self::ORGANIZER_EMAIL,
                'organizer_password' => self::PASSWORD,
                'vendor_email' => self::VENDOR_EMAIL,
                'vendor_password' => self::PASSWORD,
                'space_id' => $space->id,
                'space_name' => $space->space_size,
                'food_category_id' => $food->id,
                'thrift_category_id' => $thrift->id,
                'row_count' => 0,
                'site_count' => 0,
            ];
        }));
    }

    private function status(): int
    {
        $event = CarbootEvent::query()->where('title', self::EVENT_TITLE)->first();

        return $this->emit([
            'ok' => true,
            'database' => (string) config('database.connections.'.config('database.default').'.database'),
            'exists' => $event !== null,
            'event_id' => $event?->id,
            'row_count' => $event
                ? EventLayoutRow::query()->forEvent($event->id)->count()
                : 0,
            'site_count' => $event
                ? EventSite::query()->forEvent($event->id)->count()
                : 0,
        ]);
    }

    private function cleanupFixtures(): int
    {
        $this->purge();

        return $this->emit([
            'ok' => true,
            'database' => (string) config('database.connections.'.config('database.default').'.database'),
            'cleaned' => true,
        ]);
    }

    private function purge(): void
    {
        $eventIds = CarbootEvent::query()
            ->where('title', 'like', self::MARKER.'%')
            ->pluck('id');

        if ($eventIds->isNotEmpty()) {
            $siteIds = EventSite::query()->whereIn('carboot_event_id', $eventIds)->pluck('id');
            $bookingIds = Booking::query()->whereIn('carboot_event_id', $eventIds)->pluck('id');

            if ($bookingIds->isNotEmpty()) {
                Invoice::query()->whereIn('booking_id', $bookingIds)->delete();
                BookingDayAllocation::query()->whereIn('booking_id', $bookingIds)->delete();
                BookingAuditLog::query()->whereIn('booking_id', $bookingIds)->delete();
                Booking::query()->whereIn('id', $bookingIds)->delete();
            }

            if ($siteIds->isNotEmpty()) {
                BookingDayAllocation::query()->whereIn('event_site_id', $siteIds)->delete();
                EventSite::query()->whereIn('id', $siteIds)->delete();
            }

            EventDay::query()->whereIn('carboot_event_id', $eventIds)->delete();
            EventLayoutAuditLog::query()->whereIn('carboot_event_id', $eventIds)->delete();
            EventLayoutRow::query()->whereIn('carboot_event_id', $eventIds)->delete();
            CarbootEvent::query()->whereIn('id', $eventIds)->delete();
        }

        Space::query()->where('space_size', self::SPACE_NAME)->delete();

        $userIds = User::query()
            ->whereIn('email', [self::ORGANIZER_EMAIL, self::VENDOR_EMAIL])
            ->pluck('id');
        if ($userIds->isNotEmpty()) {
            VendorBusinessProfile::query()->whereIn('user_id', $userIds)->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();
            User::query()->whereIn('id', $userIds)->delete();
        }
    }

    private function createUser(string $email, string $name, string $role, string $vendorStatus): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'role' => $role,
            'vendor_status' => $vendorStatus,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emit(array $payload): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));
        } else {
            $this->info(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }

    private function failUnknownAction(): int
    {
        $this->error('Unknown action. Use create, status, or cleanup.');

        return self::FAILURE;
    }
}
