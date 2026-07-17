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
 * Phase 3.10 — deterministic public navigation and privacy fixtures.
 */
class E2EPublicLayoutFixtures extends Command
{
    protected $signature = 'e2e:public-layout-fixtures
                            {action=create : create, status, or cleanup}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Create or remove isolated Phase 3.10 public layout E2E fixtures';

    public const MARKER = 'E2E-P310';
    public const PUBLISHED_TITLE = self::MARKER . ' Published Visitor Map';
    public const UNPUBLISHED_TITLE = self::MARKER . ' Unpublished Visitor Map';
    public const ENDED_TITLE = self::MARKER . ' Ended Visitor Map';
    public const CLOSED_TITLE = self::MARKER . ' Closed Visitor Map';
    public const VENDOR_EMAIL = 'private-p310-vendor@example.test';
    public const ORGANIZER_EMAIL = 'e2e-p310-organizer@example.test';
    public const PASSWORD = 'P310-E2E-password';
    public const PRIVATE_OVERRIDE_REASON = 'Private P310 category override reason';
    private const SPACE_NAME = 'E2E P310 Standard';

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
                'location' => 'Isolated E2E fixture only',
                'price' => 30,
                'status' => 'Available',
            ]);
            $vendor = $this->createUser(
                self::VENDOR_EMAIL,
                'Private P310 Vendor Identity',
                'community',
                'approved',
            );
            $organizer = $this->createUser(
                self::ORGANIZER_EMAIL,
                'P310 Organizer',
                'organizer',
                'none',
            );
            VendorBusinessProfile::create([
                'user_id' => $vendor->id,
                'business_name' => 'Private P310 Business Name',
                'business_phone' => '0199999999',
                'business_category' => $thrift->label,
                'vendor_category_id' => $thrift->id,
                'description' => 'Private P310 profile text',
            ]);

            $starts = now()->addDays(12)->startOfDay()->setTime(8, 0);
            $published = $this->createEvent(
                self::PUBLISHED_TITLE,
                $starts,
                $starts->copy()->addDay()->setTime(17, 0),
                'Available',
                now()->subHour(),
            );
            $days = $this->createDays($published, $starts, 2);

            $rowA = $this->createRow($published, $thrift, 'Row A', 1, true, $organizer);
            $rowB = $this->createRow($published, $food, 'Row B', 2, true, $organizer);
            $privateRow = $this->createRow($published, $food, 'Private Row X', 3, false, $organizer);

            $sites = [
                'A01' => $this->createSite($published, $rowA, $space, 'A01', 1),
                'A02' => $this->createSite($published, $rowA, $space, 'A02', 2),
                'B01' => $this->createSite($published, $rowB, $space, 'B01', 1),
                'B02' => $this->createSite($published, $rowB, $space, 'B02', 2),
                'X01' => $this->createSite($published, $privateRow, $space, 'X01', 1),
            ];
            EventSite::create([
                'carboot_event_id' => $published->id,
                'event_layout_row_id' => null,
                'space_id' => $space->id,
                'label' => 'U99',
                'row_label' => 'Legacy',
                'position_number' => 99,
                'grid_row' => 9,
                'grid_column' => 9,
                'display_order' => 99,
                'operational_status' => EventSite::STATUS_DISABLED,
            ]);

            $booking = Booking::create([
                'user_id' => $vendor->id,
                'space_id' => $space->id,
                'carboot_event_id' => $published->id,
                'booking_date' => $starts->toDateString(),
                'vendor_category_id' => $thrift->id,
                'category_label_snapshot' => $thrift->label,
                'product_category' => $thrift->label,
                'product_details' => 'Private P310 product details',
                'approval_status' => 'Approved',
            ]);
            Invoice::create([
                'booking_id' => $booking->id,
                'amount' => 30,
                'payment_status' => 'Paid',
            ]);
            foreach ($days as $day) {
                BookingDayAllocation::create([
                    'booking_id' => $booking->id,
                    'event_day_id' => $day->id,
                    'event_site_id' => $sites['B01']->id,
                    'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
                    'reserved_at' => now()->subHour(),
                    'confirmed_at' => now(),
                    'active_lock' => 1,
                ]);
            }
            BookingCategoryOverride::create([
                'booking_id' => $booking->id,
                'booking_category_id_snapshot' => $thrift->id,
                'booking_category_label_snapshot' => $thrift->label,
                'assigned_category_id_snapshot' => $food->id,
                'assigned_category_label_snapshot' => $food->label,
                'assigned_row_ids_snapshot' => [$rowB->id],
                'assigned_row_labels_snapshot' => [$rowB->label],
                'assigned_site_ids_snapshot' => [$sites['B01']->id],
                'assigned_site_labels_snapshot' => [$sites['B01']->label],
                'reason' => self::PRIVATE_OVERRIDE_REASON,
                'applied_by_user_id' => $organizer->id,
                'applied_at' => now(),
                'status' => BookingCategoryOverride::STATUS_ACTIVE,
                'active_lock' => 1,
            ]);
            BookingAuditLog::create([
                'booking_id' => $booking->id,
                'actor_user_id' => $organizer->id,
                'action' => 'p310_private_fixture_audit',
                'from_status' => 'Approved',
                'to_status' => 'Approved',
                'revision_comment' => 'Private P310 audit text',
            ]);

            $unpublished = $this->createSimpleLayoutEvent(
                self::UNPUBLISHED_TITLE,
                $starts->copy()->addDays(3),
                'Available',
                null,
                $thrift,
                $space,
                $organizer,
                'Row U',
                'U01',
            );
            $ended = $this->createSimpleLayoutEvent(
                self::ENDED_TITLE,
                now()->subDays(3)->startOfDay()->setTime(8, 0),
                'Available',
                now()->subDays(10),
                $thrift,
                $space,
                $organizer,
                'Row E',
                'E01',
                now()->subDays(2)->setTime(17, 0),
            );
            $closed = $this->createSimpleLayoutEvent(
                self::CLOSED_TITLE,
                $starts->copy()->addDays(6),
                'Closed',
                now()->subHour(),
                $food,
                $space,
                $organizer,
                'Row C',
                'C01',
            );

            return [
                'database' => DB::connection()->getDatabaseName(),
                'published_event_id' => $published->id,
                'published_event_title' => $published->title,
                'unpublished_event_id' => $unpublished->id,
                'unpublished_event_title' => $unpublished->title,
                'ended_event_id' => $ended->id,
                'closed_event_id' => $closed->id,
                'food_category_id' => $food->id,
                'food_category_label' => $food->label,
                'thrift_category_label' => $thrift->label,
                'private_row_label' => $privateRow->label,
                'unresolved_site_label' => 'U99',
                'private_vendor_name' => $vendor->name,
                'private_vendor_email' => $vendor->email,
                'private_vendor_password' => self::PASSWORD,
                'private_override_reason' => self::PRIVATE_OVERRIDE_REASON,
            ];
        }));
    }

    private function createSimpleLayoutEvent(
        string $title,
        $starts,
        string $status,
        $publishedAt,
        VendorCategory $category,
        Space $space,
        User $organizer,
        string $rowLabel,
        string $siteLabel,
        $ends = null,
    ): CarbootEvent {
        $event = $this->createEvent(
            $title,
            $starts,
            $ends ?? $starts->copy()->setTime(17, 0),
            $status,
            $publishedAt,
        );
        $this->createDays($event, $starts, 1);
        $row = $this->createRow($event, $category, $rowLabel, 1, true, $organizer);
        $this->createSite($event, $row, $space, $siteLabel, 1);

        return $event;
    }

    private function createEvent(string $title, $starts, $ends, string $status, $publishedAt): CarbootEvent
    {
        return CarbootEvent::create([
            'title' => $title,
            'description' => self::MARKER . ' isolated public layout fixture',
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => $status,
            'max_slots' => 20,
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
            'public_layout_published_at' => $publishedAt,
            'public_layout_entrance_note' => 'Masuk melalui pintu utama CMart.',
        ]);
    }

    private function createDays(CarbootEvent $event, $starts, int $count): array
    {
        $days = [];
        for ($offset = 0; $offset < $count; $offset++) {
            $dayStart = $starts->copy()->addDays($offset);
            $days[] = EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $dayStart->toDateString(),
                'starts_at' => $dayStart,
                'ends_at' => $dayStart->copy()->setTime(17, 0),
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => $offset + 1,
            ]);
        }

        return $days;
    }

    private function createRow(
        CarbootEvent $event,
        VendorCategory $category,
        string $label,
        int $order,
        bool $public,
        User $organizer,
    ): EventLayoutRow {
        return EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'vendor_category_id' => $category->id,
            'label' => $label,
            'slug' => strtolower(str_replace(' ', '-', $label)),
            'description' => $label . ' visitor navigation area',
            'display_order' => $order,
            'is_active' => true,
            'is_public' => $public,
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);
    }

    private function createSite(
        CarbootEvent $event,
        EventLayoutRow $row,
        Space $space,
        string $label,
        int $position,
    ): EventSite {
        return EventSite::create([
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

    private function status(): int
    {
        $eventIds = CarbootEvent::query()->where('title', 'like', self::MARKER . '%')->pluck('id');
        $userIds = User::query()
            ->where('email', self::VENDOR_EMAIL)
            ->orWhere('email', self::ORGANIZER_EMAIL)
            ->pluck('id');
        $bookingIds = Booking::query()->whereIn('carboot_event_id', $eventIds)->pluck('id');

        return $this->emit([
            'database' => DB::connection()->getDatabaseName(),
            'users' => $userIds->count(),
            'vendor_business_profiles' => VendorBusinessProfile::query()->whereIn('user_id', $userIds)->count(),
            'events' => $eventIds->count(),
            'event_days' => EventDay::query()->whereIn('carboot_event_id', $eventIds)->count(),
            'layout_rows' => EventLayoutRow::query()->whereIn('carboot_event_id', $eventIds)->count(),
            'sites' => EventSite::query()->whereIn('carboot_event_id', $eventIds)->count(),
            'bookings' => $bookingIds->count(),
            'allocations' => BookingDayAllocation::query()->whereIn('booking_id', $bookingIds)->count(),
            'invoices' => Invoice::query()->whereIn('booking_id', $bookingIds)->count(),
            'booking_audits' => BookingAuditLog::query()->whereIn('booking_id', $bookingIds)->count(),
            'layout_audits' => EventLayoutAuditLog::query()->whereIn('carboot_event_id', $eventIds)->count(),
            'overrides' => BookingCategoryOverride::query()->whereIn('booking_id', $bookingIds)->count(),
        ]);
    }

    private function cleanupFixtures(): int
    {
        return $this->emit($this->purge());
    }

    private function purge(): array
    {
        return DB::transaction(function () {
            $eventIds = CarbootEvent::query()->where('title', 'like', self::MARKER . '%')->pluck('id');
            $userIds = User::query()
                ->where('email', self::VENDOR_EMAIL)
                ->orWhere('email', self::ORGANIZER_EMAIL)
                ->pluck('id');
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
        $this->error('Unknown action. Use create, status, or cleanup.');

        return self::FAILURE;
    }
}
