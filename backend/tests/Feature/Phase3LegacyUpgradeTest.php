<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use App\Models\UserBookingPreference;
use App\Models\VendorBusinessProfile;
use App\Models\VendorItem;
use App\Support\Migrations\Phase34SchemaBackfill;
use App\Support\Phase3UpgradeDatabaseGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase3LegacyUpgradeTest extends TestCase
{
    private string $originalConnection;

    public function test_pre_phase3_records_upgrade_without_data_loss_or_unknown_coercion(): void
    {
        $this->originalConnection = DB::getDefaultConnection();
        $database = Phase3UpgradeDatabaseGuard::APPROVED_DATABASE;
        Phase3UpgradeDatabaseGuard::assertSafe('mysql', $database);

        DB::statement("DROP DATABASE IF EXISTS `{$database}`");
        DB::statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $upgradeConfig = config('database.connections.mysql');
        $upgradeConfig['database'] = $database;
        config(['database.connections.phase3_upgrade' => $upgradeConfig]);
        DB::purge('phase3_upgrade');
        DB::setDefaultConnection('phase3_upgrade');

        try {
            $migrator = app('migrator');
            $migrator->setConnection('phase3_upgrade');
            $migrator->getRepository()->createRepository();
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $phase3Start = '2026_07_16_000001_create_vendor_categories_table';
            $legacyFiles = array_filter(
                $files,
                fn (string $path, string $name) => $name < $phase3Start,
                ARRAY_FILTER_USE_BOTH,
            );
            $migrator->run(array_values($legacyFiles));

            $fixture = $this->seedLegacyFixture();
            $before = $this->fixturePresence($fixture);
            $this->assertNotContains(false, $before);

            $migrator->run(array_values($files));

            $foodId = DB::table('vendor_categories')->where('slug', 'food-beverages')->value('id');
            $mixedId = DB::table('vendor_categories')->where('slug', 'mixed-others')->value('id');
            $thriftId = DB::table('vendor_categories')->where('slug', 'pre-loved-thrift')->value('id');

            $this->assertSame((int) $foodId, (int) Booking::query()->findOrFail($fixture['booking'])->vendor_category_id);
            $this->assertSame('Food & Beverages', Booking::query()->findOrFail($fixture['booking'])->category_label_snapshot);
            $this->assertSame((int) $mixedId, (int) VendorBusinessProfile::query()->findOrFail($fixture['profile'])->vendor_category_id);
            $this->assertSame((int) $thriftId, (int) VendorItem::query()->findOrFail($fixture['item'])->vendor_category_id);
            $this->assertNull(UserBookingPreference::query()->findOrFail($fixture['preference'])->vendor_category_id);
            $this->assertNotSame(
                (int) $mixedId,
                (int) UserBookingPreference::query()->findOrFail($fixture['preference'])->vendor_category_id,
            );

            $unknownAudit = DB::table('category_migration_audits')
                ->where('source_table', 'user_booking_preferences')
                ->where('source_primary_key', $fixture['preference'])
                ->first();
            $this->assertNotNull($unknownAudit);
            $this->assertSame('unresolved', $unknownAudit->mapping_status);
            $this->assertSame('unknown_value', $unknownAudit->reason_code);
            $this->assertNull($unknownAudit->matched_vendor_category_id);

            $site = EventSite::query()->findOrFail($fixture['site']);
            $this->assertNotNull($site->event_layout_row_id);
            $this->assertSame(
                $fixture['event_with_site'],
                EventLayoutRow::query()->findOrFail($site->event_layout_row_id)->carboot_event_id,
            );
            $this->assertNotNull(CarbootEvent::query()->find($fixture['event_without_site']));
            $this->assertNotContains(false, $this->fixturePresence($fixture));

            $auditCount = DB::table('category_migration_audits')->count();
            Phase34SchemaBackfill::backfillCategoryRelationships();
            Phase34SchemaBackfill::backfillEventLayoutRows();
            $this->assertSame($auditCount, DB::table('category_migration_audits')->count());
            $this->assertSame(1, EventLayoutRow::query()->where('carboot_event_id', $fixture['event_with_site'])->count());

            DB::table('user_booking_preferences')->where('id', $fixture['preference'])->update([
                'product_category' => 'Another Unknown Legacy Category',
                'vendor_category_id' => null,
            ]);
            Phase34SchemaBackfill::backfillCategoryRelationships();
            $this->assertSame(
                2,
                DB::table('category_migration_audits')
                    ->where('source_table', 'user_booking_preferences')
                    ->where('source_primary_key', $fixture['preference'])
                    ->count(),
            );
            $this->assertNull(UserBookingPreference::query()->findOrFail($fixture['preference'])->vendor_category_id);

            $this->assertCount(count($files), $migrator->getRepository()->getRan());
        } finally {
            $this->cleanupFixture();
            DB::setDefaultConnection($this->originalConnection);
            DB::purge('phase3_upgrade');
            app('migrator')->setConnection($this->originalConnection);
        }
    }

    /**
     * @return array<string, int>
     */
    private function seedLegacyFixture(): array
    {
        $user = User::create([
            'name' => 'Phase 3 Upgrade Vendor',
            'email' => 'phase3-upgrade-vendor@example.test',
            'password' => bcrypt('password123'),
            'role' => 'community',
            'vendor_status' => 'approved',
        ]);
        $space = Space::create([
            'space_size' => 'Phase 3 Upgrade Space',
            'price' => 25,
            'status' => 'Available',
        ]);
        $event = CarbootEvent::create([
            'title' => 'Phase 3 Upgrade Event With Legacy Site',
            'starts_at' => now()->addDays(20),
            'ends_at' => now()->addDays(21),
            'status' => 'Available',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]);
        $emptyEvent = CarbootEvent::create([
            'title' => 'Phase 3 Upgrade Event Without Sites',
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(31),
            'status' => 'Available',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => now()->addDays(20)->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Legacy booking retained through Phase 3 upgrade.',
            'approval_status' => 'Pending_Organizer',
        ]);
        $profile = VendorBusinessProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Phase 3 Upgrade Business',
            'business_phone' => '0191111111',
            'business_category' => 'Others',
        ]);
        $preference = UserBookingPreference::create([
            'user_id' => $user->id,
            'name' => 'Legacy preference',
            'product_category' => 'Mystery Wares',
            'tapak_count' => 1,
        ]);
        $item = VendorItem::create([
            'user_id' => $user->id,
            'name' => 'Legacy thrift item',
            'category' => 'Pre-loved / Thrift',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => 15,
            'status' => 'active',
        ]);
        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => 'L01',
            'row_label' => 'Legacy Row',
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);

        return [
            'user' => $user->id,
            'space' => $space->id,
            'event_with_site' => $event->id,
            'event_without_site' => $emptyEvent->id,
            'booking' => $booking->id,
            'profile' => $profile->id,
            'preference' => $preference->id,
            'item' => $item->id,
            'site' => $site->id,
        ];
    }

    /**
     * @param  array<string, int>  $fixture
     * @return array<string, bool>
     */
    private function fixturePresence(array $fixture): array
    {
        return [
            'user' => User::query()->whereKey($fixture['user'])->exists(),
            'space' => Space::query()->whereKey($fixture['space'])->exists(),
            'event_with_site' => CarbootEvent::query()->whereKey($fixture['event_with_site'])->exists(),
            'event_without_site' => CarbootEvent::query()->whereKey($fixture['event_without_site'])->exists(),
            'booking' => Booking::query()->whereKey($fixture['booking'])->exists(),
            'profile' => VendorBusinessProfile::query()->whereKey($fixture['profile'])->exists(),
            'preference' => UserBookingPreference::query()->whereKey($fixture['preference'])->exists(),
            'item' => VendorItem::query()->whereKey($fixture['item'])->exists(),
            'site' => EventSite::query()->whereKey($fixture['site'])->exists(),
        ];
    }

    private function cleanupFixture(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasTable('category_migration_audits')) {
            DB::table('category_migration_audits')->whereIn('source_table', [
                'bookings',
                'vendor_business_profiles',
                'user_booking_preferences',
                'vendor_items',
            ])->delete();
        }

        foreach ([
            'booking_day_allocations',
            'booking_category_overrides',
            'invoices',
            'booking_audit_logs',
            'bookings',
            'vendor_items',
            'user_booking_preferences',
            'vendor_business_profiles',
            'event_layout_audit_logs',
            'carboot_events',
            'users',
            'spaces',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }
}
