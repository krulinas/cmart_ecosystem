<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\CategoryMigrationAudit;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use App\Models\UserBookingPreference;
use App\Models\VendorBusinessProfile;
use App\Models\VendorCategory;
use App\Models\VendorItem;
use App\Support\Migrations\CategoryLegacyMapper;
use App\Support\Migrations\Phase34SchemaBackfill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\TestCase;

class Phase34CategoryAndLayoutSchemaTest extends TestCase
{
    use CleansUpTestFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    public function test_schema_columns_and_nullability_exist(): void
    {
        $this->assertTrue(Schema::hasTable('vendor_categories'));
        $this->assertTrue(Schema::hasTable('event_layout_rows'));
        $this->assertTrue(Schema::hasTable('category_migration_audits'));

        $this->assertTrue(Schema::hasColumns('bookings', [
            'vendor_category_id',
            'category_label_snapshot',
            'product_category',
        ]));
        $this->assertTrue(Schema::hasColumn('event_sites', 'event_layout_row_id'));
        $this->assertTrue(Schema::hasColumn('event_sites', 'row_label'));
        $this->assertTrue(Schema::hasColumn('vendor_business_profiles', 'vendor_category_id'));
        $this->assertTrue(Schema::hasColumn('user_booking_preferences', 'vendor_category_id'));
        $this->assertTrue(Schema::hasColumn('vendor_items', 'vendor_category_id'));

        $bookingColumns = Schema::getColumnType('bookings', 'vendor_category_id');
        $this->assertNotNull($bookingColumns);

        // Nullable: insert booking without FK/snapshot still works (legacy string only).
        $user = $this->trackUser(User::factory()->community()->create());
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34 Schema Space',
            'price' => 10,
            'status' => 'Available',
        ]);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34 Schema Event',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
            'status' => 'Available',
            'description' => 'schema nullability',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $booking = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => now()->addDays(3)->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Schema nullability check for Phase 3.4 additive columns.',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;

        $this->assertNull($booking->fresh()->vendor_category_id);
        $this->assertNull($booking->fresh()->category_label_snapshot);
        $this->assertSame('Food & Beverages', $booking->fresh()->product_category);
    }

    public function test_exactly_seven_canonical_categories_seeded(): void
    {
        $categories = VendorCategory::query()->ordered()->get();

        $this->assertCount(7, $categories);
        $this->assertSame(
            array_column(CategoryLegacyMapper::canonicalCategories(), 'slug'),
            $categories->pluck('slug')->all(),
        );
        $this->assertSame(
            array_column(CategoryLegacyMapper::canonicalCategories(), 'label'),
            $categories->pluck('label')->all(),
        );
        $this->assertFalse($categories->contains(fn ($c) => $c->label === 'Others'));
        $this->assertFalse($categories->contains(fn ($c) => $c->label === 'Food & Drinks'));
        $this->assertFalse($categories->contains(fn ($c) => $c->label === 'Preloved Clothes'));
        $this->assertTrue($categories->contains(fn ($c) => $c->label === 'Mixed / Others'));
        $this->assertTrue($categories->contains(fn ($c) => $c->label === 'Household Items'));
    }

    public function test_canonical_category_seed_is_idempotent(): void
    {
        $before = VendorCategory::query()->count();
        $inserted = Phase34SchemaBackfill::seedCanonicalCategories();
        $after = VendorCategory::query()->count();

        $this->assertSame(0, $inserted);
        $this->assertSame($before, $after);
        $this->assertSame(7, $after);
    }

    public function test_category_slug_and_label_uniqueness(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        VendorCategory::create([
            'slug' => 'food-beverages',
            'label' => 'Duplicate Food Label',
            'display_order' => 99,
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    public function test_category_backfill_maps_exact_alias_and_unknown(): void
    {
        $user = $this->trackUser(User::factory()->community()->create());
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34 Backfill Space',
            'price' => 12,
            'status' => 'Available',
        ]);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34 Backfill Event',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
            'status' => 'Available',
            'description' => 'category backfill',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $mapped = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => now()->addDays(5)->toDateString(),
            'product_category' => '  Food   &   Beverages  ',
            'product_details' => 'Exact mapped booking for Phase 3.4 backfill validation.',
            'approval_status' => 'Pending_Organizer',
        ]);
        $alias = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => now()->addDays(5)->toDateString(),
            'product_category' => 'Others',
            'product_details' => 'Alias mapped booking for Phase 3.4 backfill validation.',
            'approval_status' => 'Pending_Organizer',
        ]);
        $unknown = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => now()->addDays(5)->toDateString(),
            'product_category' => 'Food & Drinks',
            'product_details' => 'Unknown category must remain unresolved in Phase 3.4.',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds = [$mapped->id, $alias->id, $unknown->id];

        // Force legacy strings without FK (simulate pre-backfill rows).
        DB::table('bookings')->whereIn('id', $this->createdBookingIds)->update([
            'vendor_category_id' => null,
            'category_label_snapshot' => null,
        ]);

        Phase34SchemaBackfill::backfillCategoryRelationships();
        Phase34SchemaBackfill::backfillCategoryRelationships(); // idempotent

        $mapped = $mapped->fresh();
        $alias = $alias->fresh();
        $unknown = $unknown->fresh();

        $this->assertNotNull($mapped->vendor_category_id);
        $this->assertSame('Food & Beverages', $mapped->category_label_snapshot);
        $this->assertSame('  Food   &   Beverages  ', $mapped->product_category);

        $this->assertSame(
            VendorCategory::query()->where('slug', 'mixed-others')->value('id'),
            $alias->vendor_category_id,
        );
        $this->assertSame('Mixed / Others', $alias->category_label_snapshot);
        $this->assertSame('Others', $alias->product_category);

        $this->assertNull($unknown->vendor_category_id);
        $this->assertNull($unknown->category_label_snapshot);
        $this->assertSame('Food & Drinks', $unknown->product_category);

        $auditCount = CategoryMigrationAudit::query()
            ->where('source_table', 'bookings')
            ->whereIn('source_primary_key', $this->createdBookingIds)
            ->count();
        $this->assertSame(3, $auditCount);

        $unresolved = CategoryMigrationAudit::query()
            ->where('source_table', 'bookings')
            ->where('source_primary_key', $unknown->id)
            ->where('mapping_status', CategoryLegacyMapper::STATUS_UNRESOLVED)
            ->first();
        $this->assertNotNull($unresolved);
        $this->assertSame('Food & Drinks', $unresolved->normalized_value);
    }

    public function test_profile_item_and_preference_backfill(): void
    {
        $user = $this->trackUser(User::factory()->community()->create());

        $profile = VendorBusinessProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Phase34 Profile',
            'business_phone' => '0123456789',
            'business_category' => 'Household Items',
            'description' => 'test',
        ]);

        $preference = UserBookingPreference::create([
            'user_id' => $user->id,
            'name' => 'Pref',
            'product_category' => 'food & beverages', // case mismatch → unresolved
            'specific_products' => 'x',
            'tapak_count' => 1,
            'remember_enabled' => true,
        ]);

        $item = VendorItem::create([
            'user_id' => $user->id,
            'name' => 'Phase34 Item',
            'category' => 'Electronics & Gadgets',
            'condition' => 'Good',
            'pricing_type' => 'fixed',
            'price' => 5,
            'status' => 'active',
        ]);

        Phase34SchemaBackfill::backfillCategoryRelationships();

        $this->assertSame(
            VendorCategory::query()->where('slug', 'household-items')->value('id'),
            $profile->fresh()->vendor_category_id,
        );
        $this->assertSame('Household Items', $profile->fresh()->business_category);

        $this->assertNull($preference->fresh()->vendor_category_id);
        $this->assertSame('food & beverages', $preference->fresh()->product_category);

        $this->assertSame(
            VendorCategory::query()->where('slug', 'electronics-gadgets')->value('id'),
            $item->fresh()->vendor_category_id,
        );
        $this->assertSame('Electronics & Gadgets', $item->fresh()->category);

        $profile->delete();
        $preference->delete();
        $item->delete();
    }

    public function test_event_layout_row_backfill_from_sites(): void
    {
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34 Row Space',
            'price' => 15,
            'status' => 'Available',
        ]);

        $eventA = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34 Layout Event A',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(11),
            'status' => 'Available',
            'description' => 'row backfill A',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));
        $eventB = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34 Layout Event B',
            'starts_at' => now()->addDays(12),
            'ends_at' => now()->addDays(13),
            'status' => 'Available',
            'description' => 'row backfill B',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $sites = [];
        foreach ([
            [$eventA->id, 'A1', '  Row   A  ', 1, 1, 1, 10],
            [$eventA->id, 'A2', 'Row A', 2, 1, 2, 20],
            [$eventA->id, 'B1', 'Row B', 1, 2, 1, 30],
            [$eventB->id, 'A1', 'Row A', 1, 1, 1, 5],
        ] as [$eventId, $label, $rowLabel, $pos, $gridRow, $gridCol, $displayOrder]) {
            $site = EventSite::create([
                'carboot_event_id' => $eventId,
                'space_id' => $space->id,
                'label' => $label,
                'row_label' => $rowLabel,
                'position_number' => $pos,
                'grid_row' => $gridRow,
                'grid_column' => $gridCol,
                'display_order' => $displayOrder,
                'operational_status' => EventSite::STATUS_ACTIVE,
            ]);
            $this->createdSiteIds[] = $site->id;
            $sites[] = $site;
        }

        // Invalid blank row_label site
        $blank = EventSite::create([
            'carboot_event_id' => $eventA->id,
            'space_id' => $space->id,
            'label' => 'Z9',
            'row_label' => '   ',
            'position_number' => 9,
            'grid_row' => 9,
            'grid_column' => 9,
            'display_order' => 99,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->createdSiteIds[] = $blank->id;

        $stats = Phase34SchemaBackfill::backfillEventLayoutRows();
        $statsAgain = Phase34SchemaBackfill::backfillEventLayoutRows();

        $this->assertSame(3, EventLayoutRow::query()->whereIn('carboot_event_id', [$eventA->id, $eventB->id])->count());
        $this->assertSame(0, $statsAgain['rows_created']);

        $rowA = EventLayoutRow::query()->where('carboot_event_id', $eventA->id)->where('label', 'Row A')->first();
        $rowB = EventLayoutRow::query()->where('carboot_event_id', $eventA->id)->where('label', 'Row B')->first();
        $rowAOtherEvent = EventLayoutRow::query()->where('carboot_event_id', $eventB->id)->where('label', 'Row A')->first();

        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);
        $this->assertNotNull($rowAOtherEvent);
        $this->assertNotSame($rowA->id, $rowAOtherEvent->id);
        $this->assertNull($rowA->vendor_category_id);
        $this->assertTrue($rowA->is_active);
        $this->assertTrue($rowA->is_public);

        $this->assertSame($rowA->id, $sites[0]->fresh()->event_layout_row_id);
        $this->assertSame($rowA->id, $sites[1]->fresh()->event_layout_row_id);
        $this->assertSame($rowB->id, $sites[2]->fresh()->event_layout_row_id);
        $this->assertSame($rowAOtherEvent->id, $sites[3]->fresh()->event_layout_row_id);
        $this->assertNull($blank->fresh()->event_layout_row_id);
        $this->assertSame(1, $stats['unresolved_sites']);

        // Preserve original stored row_label strings on sites (including whitespace variants).
        $this->assertSame('  Row   A  ', $sites[0]->fresh()->row_label);
        $this->assertSame('Row A', $sites[1]->fresh()->row_label);

        // Phase 3.4A: do not hard-delete rows while sites still reference them.
        // Fixture cleanup deletes sites first, then events (cascading empty rows).
    }

    public function test_event_layout_row_label_and_slug_unique_per_event(): void
    {
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34 Unique Row Event',
            'starts_at' => now()->addDays(20),
            'ends_at' => now()->addDays(21),
            'status' => 'Available',
            'description' => 'unique constraints',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'label' => 'Row A',
            'slug' => 'row-a',
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'label' => 'Row A',
            'slug' => 'row-a-2',
            'display_order' => 2,
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    public function test_model_relationships_resolve(): void
    {
        $category = VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34 Relation Event',
            'starts_at' => now()->addDays(25),
            'ends_at' => now()->addDays(26),
            'status' => 'Available',
            'description' => 'relationships',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $row = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'vendor_category_id' => $category->id,
            'label' => 'Row R',
            'slug' => 'row-r',
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->assertTrue($event->eventLayoutRows()->whereKey($row->id)->exists());
        $this->assertTrue($category->eventLayoutRows()->whereKey($row->id)->exists());
        $this->assertSame($category->id, $row->vendorCategory->id);
        $this->assertSame($event->id, $row->carbootEvent->id);

        $row->delete();
    }

    public function test_slug_collision_suffix_is_deterministic(): void
    {
        $used = ['row-a' => true];
        [$slug, $collision] = Phase34SchemaBackfill::uniqueSlug('Row A', $used);

        $this->assertTrue($collision);
        $this->assertSame('row-a-2', $slug);
    }
}
