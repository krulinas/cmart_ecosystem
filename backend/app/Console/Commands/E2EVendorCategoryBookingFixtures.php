<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingCategoryOverride;
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
 * Phase 3.9 — deterministic category-first vendor booking fixtures.
 */
class E2EVendorCategoryBookingFixtures extends Command
{
    protected $signature = 'e2e:vendor-category-booking-fixtures
                            {action=create : create, occupy, status, or cleanup}
                            {--site=B02 : Site label used by the occupy action}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Create or remove isolated Phase 3.9 vendor category booking E2E fixtures';

    public const MARKER = 'E2E-P39';
    public const EVENT_TITLE = self::MARKER . ' Category Booking Weekend';
    public const VENDOR_EMAIL = 'e2e-p39-vendor@example.test';
    public const ORGANIZER_EMAIL = 'e2e-p39-organizer@example.test';
    public const MANAGEMENT_EMAIL = 'e2e-p39-management@example.test';
    public const COMPETITOR_EMAIL = 'e2e-p39-competitor@example.test';
    public const PASSWORD = 'P39-E2E-password';
    private const SPACE_NAME = 'E2E P39 Standard';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'create' => $this->createFixtures(),
            'occupy' => $this->occupySite(),
            'status' => $this->status(),
            'cleanup' => $this->cleanupFixtures(),
            default => $this->failUnknownAction(),
        };
    }

    private function createFixtures(): int
    {
        $this->purge();

        $payload = DB::transaction(function () {
            $food = VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
            $thrift = VendorCategory::query()->where('slug', 'pre-loved-thrift')->firstOrFail();
            $space = Space::create([
                'space_size' => self::SPACE_NAME,
                'location' => 'CMart E2E isolated fixture',
                'price' => 30.00,
                'status' => 'Available',
            ]);

            $vendor = $this->createUser(
                self::VENDOR_EMAIL,
                'Phase 3.9 E2E Vendor',
                'community',
                'approved',
            );
            $organizer = $this->createUser(
                self::ORGANIZER_EMAIL,
                'Phase 3.9 E2E Organizer',
                'organizer',
                'none',
            );
            $management = $this->createUser(
                self::MANAGEMENT_EMAIL,
                'Phase 3.9 E2E CMart Management',
                'cmart_management',
                'none',
            );
            $competitor = $this->createUser(
                self::COMPETITOR_EMAIL,
                'Phase 3.9 E2E Competitor',
                'community',
                'approved',
            );

            VendorBusinessProfile::create([
                'user_id' => $vendor->id,
                'business_name' => 'Phase 3.9 Food Vendor',
                'business_phone' => '0000000000',
                'business_category' => $food->label,
                'vendor_category_id' => $food->id,
                'description' => self::MARKER . ' profile category suggestion',
            ]);

            $starts = now()->addDays(14)->startOfDay()->setTime(8, 0);
            $event = CarbootEvent::create([
                'title' => self::EVENT_TITLE,
                'description' => self::MARKER . ' isolated browser fixture',
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->addDay()->setTime(17, 0),
                'status' => 'Available',
                'max_slots' => 20,
                'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
            ]);

            $dayIds = [];
            foreach ([0, 1] as $offset) {
                $dayStart = $starts->copy()->addDays($offset);
                $dayIds[] = EventDay::create([
                    'carboot_event_id' => $event->id,
                    'operational_date' => $dayStart->toDateString(),
                    'starts_at' => $dayStart,
                    'ends_at' => $dayStart->copy()->setTime(17, 0),
                    'operational_status' => EventDay::STATUS_ACTIVE,
                    'display_order' => $offset + 1,
                ])->id;
            }

            $rowA = EventLayoutRow::create([
                'carboot_event_id' => $event->id,
                'vendor_category_id' => $thrift->id,
                'label' => 'Row A',
                'slug' => 'row-a',
                'description' => 'Pre-loved and thrift vendors',
                'display_order' => 1,
                'is_active' => true,
                'is_public' => true,
                'created_by' => $organizer->id,
                'updated_by' => $organizer->id,
            ]);
            $rowB = EventLayoutRow::create([
                'carboot_event_id' => $event->id,
                'vendor_category_id' => $food->id,
                'label' => 'Row B',
                'slug' => 'row-b',
                'description' => 'Food and beverage vendors',
                'display_order' => 2,
                'is_active' => true,
                'is_public' => true,
                'created_by' => $organizer->id,
                'updated_by' => $organizer->id,
            ]);

            $sites = [];
            foreach ([
                [$rowA, 'A01', 1],
                [$rowA, 'A02', 2],
                [$rowB, 'B01', 1],
                [$rowB, 'B02', 2],
                [$rowB, 'B03', 3],
                [$rowB, 'B04', 4],
            ] as [$row, $label, $position]) {
                $site = EventSite::create([
                    'carboot_event_id' => $event->id,
                    'event_layout_row_id' => $row->id,
                    'space_id' => $space->id,
                    'label' => $label,
                    'row_label' => $row->label,
                    'position_number' => $position,
                    'grid_row' => $row->display_order,
                    'grid_column' => $position,
                    'display_order' => $position,
                    'operational_status' => EventSite::STATUS_ACTIVE,
                ]);
                $sites[$label] = $site;
            }

            $this->createOccupancy($competitor, $event, $food, $space, $sites['B04'], $dayIds, 'baseline');

            return [
                'database' => DB::connection()->getDatabaseName(),
                'event_id' => $event->id,
                'event_title' => $event->title,
                'vendor_email' => self::VENDOR_EMAIL,
                'vendor_password' => self::PASSWORD,
                'organizer_email' => self::ORGANIZER_EMAIL,
                'organizer_password' => self::PASSWORD,
                'cmart_management_email' => self::MANAGEMENT_EMAIL,
                'cmart_management_password' => self::PASSWORD,
                'food_category_id' => $food->id,
                'food_category_label' => $food->label,
                'thrift_category_id' => $thrift->id,
                'thrift_category_label' => $thrift->label,
                'row_a_id' => $rowA->id,
                'row_b_id' => $rowB->id,
                'site_ids' => collect($sites)->map->id->all(),
                'day_ids' => $dayIds,
                'space_id' => $space->id,
                'unit_price' => '30.00',
            ];
        });

        return $this->emit($payload);
    }

    private function occupySite(): int
    {
        $siteLabel = strtoupper(trim((string) $this->option('site')));
        $event = CarbootEvent::query()->where('title', self::EVENT_TITLE)->firstOrFail();
        $site = EventSite::query()
            ->where('carboot_event_id', $event->id)
            ->where('label', $siteLabel)
            ->firstOrFail();
        $food = VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
        $competitor = User::query()->where('email', self::COMPETITOR_EMAIL)->firstOrFail();
        $dayIds = EventDay::query()->where('carboot_event_id', $event->id)->pluck('id')->all();

        $booking = DB::transaction(fn () => $this->createOccupancy(
            $competitor,
            $event,
            $food,
            $site->space,
            $site,
            $dayIds,
            'conflict-' . strtolower($siteLabel),
        ));

        return $this->emit([
            'database' => DB::connection()->getDatabaseName(),
            'booking_id' => $booking->id,
            'site_id' => $site->id,
            'site_label' => $site->label,
        ]);
    }

    private function status(): int
    {
        $eventIds = CarbootEvent::query()->where('title', 'like', self::MARKER . '%')->pluck('id');
        $bookingIds = Booking::query()->whereIn('carboot_event_id', $eventIds)->pluck('id');
        $vendor = User::query()->where('email', self::VENDOR_EMAIL)->first();
        $vendorBookings = $vendor
            ? Booking::query()->where('user_id', $vendor->id)->orderBy('id')->get()
            : collect();
        $latestVendorBooking = $vendorBookings->last();

        return $this->emit([
            'database' => DB::connection()->getDatabaseName(),
            'users' => User::query()->where('email', 'like', 'e2e-p39-%')->count(),
            'events' => $eventIds->count(),
            'event_days' => EventDay::query()->whereIn('carboot_event_id', $eventIds)->count(),
            'layout_rows' => EventLayoutRow::query()->whereIn('carboot_event_id', $eventIds)->count(),
            'sites' => EventSite::query()->whereIn('carboot_event_id', $eventIds)->count(),
            'bookings' => $bookingIds->count(),
            'allocations' => BookingDayAllocation::query()->whereIn('booking_id', $bookingIds)->count(),
            'invoices' => Invoice::query()->whereIn('booking_id', $bookingIds)->count(),
            'overrides' => BookingCategoryOverride::query()->whereIn('booking_id', $bookingIds)->count(),
            'vendor_bookings' => $vendorBookings->count(),
            'vendor_invoices' => Invoice::query()
                ->whereIn('booking_id', $vendorBookings->pluck('id'))
                ->count(),
            'vendor_allocations' => BookingDayAllocation::query()
                ->whereIn('booking_id', $vendorBookings->pluck('id'))
                ->count(),
            'latest_vendor_booking_id' => $latestVendorBooking?->id,
            'latest_vendor_category_id' => $latestVendorBooking?->vendor_category_id,
            'latest_vendor_category_snapshot' => $latestVendorBooking?->category_label_snapshot,
            'latest_vendor_product_category' => $latestVendorBooking?->product_category,
            'latest_vendor_status' => $latestVendorBooking?->approval_status,
        ]);
    }

    private function cleanupFixtures(): int
    {
        return $this->emit($this->purge());
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
     * @param  list<int>  $dayIds
     */
    private function createOccupancy(
        User $user,
        CarbootEvent $event,
        VendorCategory $category,
        Space $space,
        EventSite $site,
        array $dayIds,
        string $suffix,
    ): Booking {
        $booking = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => $event->starts_at->toDateString(),
            'vendor_category_id' => $category->id,
            'category_label_snapshot' => $category->label,
            'product_category' => $category->label,
            'product_details' => self::MARKER . ' occupied ' . $suffix,
            'approval_status' => 'Approved',
        ]);
        Invoice::create([
            'booking_id' => $booking->id,
            'amount' => $space->price,
            'payment_status' => 'Unpaid',
        ]);
        foreach ($dayIds as $dayId) {
            BookingDayAllocation::create([
                'booking_id' => $booking->id,
                'event_day_id' => $dayId,
                'event_site_id' => $site->id,
                'allocation_status' => BookingDayAllocation::STATUS_RESERVED,
                'reserved_at' => now(),
                'active_lock' => 1,
            ]);
        }

        return $booking;
    }

    /**
     * @return array<string, int|string>
     */
    private function purge(): array
    {
        return DB::transaction(function () {
            $eventIds = CarbootEvent::query()->where('title', 'like', self::MARKER . '%')->pluck('id');
            $userIds = User::query()->where('email', 'like', 'e2e-p39-%')->pluck('id');
            $bookingIds = Booking::query()
                ->whereIn('carboot_event_id', $eventIds)
                ->orWhereIn('user_id', $userIds)
                ->pluck('id');

            $deleted = [
                'database' => DB::connection()->getDatabaseName(),
                'booking_category_overrides' => BookingCategoryOverride::query()->whereIn('booking_id', $bookingIds)->delete(),
                'booking_day_allocations' => BookingDayAllocation::query()->whereIn('booking_id', $bookingIds)->delete(),
                'invoices' => Invoice::query()->whereIn('booking_id', $bookingIds)->delete(),
                'booking_audit_logs' => BookingAuditLog::query()->whereIn('booking_id', $bookingIds)->delete(),
                'bookings' => Booking::query()->whereIn('id', $bookingIds)->delete(),
                'event_layout_audit_logs' => EventLayoutAuditLog::query()->whereIn('carboot_event_id', $eventIds)->delete(),
                'event_sites' => EventSite::query()->whereIn('carboot_event_id', $eventIds)->delete(),
                'event_layout_rows' => EventLayoutRow::query()->whereIn('carboot_event_id', $eventIds)->delete(),
                'event_days' => EventDay::query()->whereIn('carboot_event_id', $eventIds)->delete(),
                'carboot_events' => CarbootEvent::query()->whereIn('id', $eventIds)->delete(),
                'vendor_business_profiles' => VendorBusinessProfile::query()->whereIn('user_id', $userIds)->delete(),
            ];

            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();
            $deleted['users'] = User::query()->whereIn('id', $userIds)->delete();
            $deleted['spaces'] = Space::query()->where('space_size', self::SPACE_NAME)->delete();

            return $deleted;
        });
    }

    private function emit(array $payload): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Field', 'Value'],
                collect($payload)->map(fn ($value, $key) => [$key, $value])->values()->all(),
            );
        }

        return self::SUCCESS;
    }

    private function failUnknownAction(): int
    {
        $this->error('Unknown action. Use create, occupy, status, or cleanup.');

        return self::FAILURE;
    }
}
