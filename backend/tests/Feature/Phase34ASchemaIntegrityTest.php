<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\CategoryMigrationAudit;
use App\Models\EventDay;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Space;
use App\Models\User;
use App\Support\Migrations\CategoryLegacyMapper;
use App\Support\Migrations\Phase34SchemaBackfill;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\TestCase;

/**
 * Phase 3.4A — restrictive row FK + append-only category migration audits.
 */
class Phase34ASchemaIntegrityTest extends TestCase
{
    use CleansUpTestFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    public function test_layout_row_fk_is_nullable_and_restrictive(): void
    {
        $this->assertTrue(Schema::hasColumn('event_sites', 'event_layout_row_id'));
        $this->assertTrue(Schema::hasColumn('event_sites', 'row_label'));
        $this->assertTrue(Schema::hasColumn('category_migration_audits', 'normalized_value_hash'));

        $fk = collect(DB::select("
            SELECT CONSTRAINT_NAME, DELETE_RULE, REFERENCED_TABLE_NAME
            FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'event_sites'
              AND CONSTRAINT_NAME = 'event_sites_event_layout_row_id_foreign'
        "))->first();

        $this->assertNotNull($fk);
        $this->assertSame('event_layout_rows', $fk->REFERENCED_TABLE_NAME);
        $this->assertSame('RESTRICT', strtoupper($fk->DELETE_RULE));
    }

    public function test_empty_layout_row_may_be_hard_deleted(): void
    {
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34A Empty Row Event',
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(31),
            'status' => 'Available',
            'description' => 'empty row delete',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $row = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'label' => 'Empty Row',
            'slug' => 'empty-row',
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);

        $rowId = $row->id;
        $row->delete();

        $this->assertNull(EventLayoutRow::query()->find($rowId));
    }

    public function test_layout_row_with_sites_cannot_be_hard_deleted(): void
    {
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34A Restrict Space',
            'price' => 18,
            'status' => 'Available',
        ]);

        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34A Restrict Row Event',
            'starts_at' => now()->addDays(32),
            'ends_at' => now()->addDays(33),
            'status' => 'Available',
            'description' => 'restrict row delete',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $row = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'label' => 'Occupied Row',
            'slug' => 'occupied-row',
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);

        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => 'R01',
            'row_label' => 'Occupied Row',
            'event_layout_row_id' => $row->id,
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->createdSiteIds[] = $site->id;

        try {
            EventLayoutRow::query()->whereKey($row->id)->delete();
            $this->fail('Expected QueryException when deleting a row that still has sites.');
        } catch (QueryException $exception) {
            $this->assertTrue(
                str_contains(strtolower($exception->getMessage()), 'foreign key')
                || ($exception->errorInfo[1] ?? null) === 1451
                || ($exception->errorInfo[0] ?? null) === '23000',
                $exception->getMessage(),
            );
        }

        $this->assertNotNull(EventLayoutRow::query()->find($row->id));
        $this->assertSame($row->id, $site->fresh()->event_layout_row_id);
        $this->assertNotNull(EventSite::query()->find($site->id));
        $this->assertSame('Occupied Row', $site->fresh()->row_label);
    }

    public function test_site_may_be_reassigned_to_another_valid_row(): void
    {
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34A Reassign Space',
            'price' => 19,
            'status' => 'Available',
        ]);

        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34A Reassign Event',
            'starts_at' => now()->addDays(34),
            'ends_at' => now()->addDays(35),
            'status' => 'Available',
            'description' => 'site reassignment',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $rowA = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'label' => 'Row A',
            'slug' => 'row-a-reassign',
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);
        $rowB = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'label' => 'Row B',
            'slug' => 'row-b-reassign',
            'display_order' => 2,
            'is_active' => true,
            'is_public' => true,
        ]);

        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => 'A01',
            'row_label' => 'Row A',
            'event_layout_row_id' => $rowA->id,
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->createdSiteIds[] = $site->id;

        $site->forceFill(['event_layout_row_id' => $rowB->id])->save();

        $this->assertSame($rowB->id, $site->fresh()->event_layout_row_id);

        // Row A is now empty and may be deleted.
        $rowA->delete();
        $this->assertNull(EventLayoutRow::query()->find($rowA->id));
        $this->assertSame($rowB->id, $site->fresh()->event_layout_row_id);
    }

    public function test_event_with_rows_and_unallocated_sites_can_be_deleted(): void
    {
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34A Event Delete Space',
            'price' => 21,
            'status' => 'Available',
        ]);

        $event = CarbootEvent::create([
            'title' => 'Phase34A Cascade Event',
            'starts_at' => now()->addDays(36),
            'ends_at' => now()->addDays(37),
            'status' => 'Available',
            'description' => 'event cascade with rows+sites',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]);

        $row = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'label' => 'Cascade Row',
            'slug' => 'cascade-row',
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);

        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => 'C01',
            'row_label' => 'Cascade Row',
            'event_layout_row_id' => $row->id,
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);

        $eventId = $event->id;
        $rowId = $row->id;
        $siteId = $site->id;

        $event->delete();

        $this->assertNull(CarbootEvent::query()->find($eventId));
        $this->assertNull(EventSite::query()->find($siteId));
        $this->assertNull(EventLayoutRow::query()->find($rowId));
    }

    public function test_event_with_allocation_history_remains_protected(): void
    {
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34A Alloc Protect Space',
            'price' => 22,
            'status' => 'Available',
        ]);

        $user = $this->trackUser(User::factory()->community()->create());
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34A Alloc History Event',
            'starts_at' => now()->addDays(38),
            'ends_at' => now()->addDays(39),
            'status' => 'Available',
            'description' => 'allocation history protect',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $row = EventLayoutRow::create([
            'carboot_event_id' => $event->id,
            'label' => 'Alloc Row',
            'slug' => 'alloc-row',
            'display_order' => 1,
            'is_active' => true,
            'is_public' => true,
        ]);

        $site = EventSite::create([
            'carboot_event_id' => $event->id,
            'space_id' => $space->id,
            'label' => 'H01',
            'row_label' => 'Alloc Row',
            'event_layout_row_id' => $row->id,
            'position_number' => 1,
            'grid_row' => 1,
            'grid_column' => 1,
            'display_order' => 1,
            'operational_status' => EventSite::STATUS_ACTIVE,
        ]);
        $this->createdSiteIds[] = $site->id;

        $day = EventDay::create([
            'carboot_event_id' => $event->id,
            'operational_date' => now()->addDays(38)->toDateString(),
            'starts_at' => now()->addDays(38)->setTime(9, 0),
            'ends_at' => now()->addDays(38)->setTime(17, 0),
            'operational_status' => EventDay::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
        $this->createdDayIds[] = $day->id;

        $booking = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => now()->addDays(38)->toDateString(),
            'product_category' => 'Household Items',
            'product_details' => 'Allocation history must block destructive cascades.',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;

        $allocation = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
            'allocation_status' => BookingDayAllocation::STATUS_RESERVED,
            'reserved_at' => now(),
            'active_lock' => 1,
        ]);
        $this->createdAllocationIds[] = $allocation->id;

        try {
            EventLayoutRow::query()->whereKey($row->id)->delete();
            $this->fail('Expected QueryException deleting row with allocated site.');
        } catch (QueryException $exception) {
            $this->assertTrue(
                str_contains(strtolower($exception->getMessage()), 'foreign key')
                || ($exception->errorInfo[1] ?? null) === 1451,
            );
        }

        $this->assertSame($row->id, $site->fresh()->event_layout_row_id);
        $this->assertSame($site->id, $allocation->fresh()->event_site_id);

        try {
            CarbootEvent::query()->whereKey($event->id)->delete();
            $this->fail('Expected QueryException deleting event with allocation history.');
        } catch (QueryException $exception) {
            $this->assertTrue(
                str_contains(strtolower($exception->getMessage()), 'foreign key')
                || ($exception->errorInfo[1] ?? null) === 1451
                || ($exception->errorInfo[0] ?? null) === '23000',
                $exception->getMessage(),
            );
        }

        $this->assertNotNull(CarbootEvent::query()->find($event->id));
        $this->assertNotNull(BookingDayAllocation::query()->find($allocation->id));
    }

    public function test_audit_append_only_same_value_rerun_is_idempotent(): void
    {
        $user = $this->trackUser(User::factory()->community()->create());
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34A Audit Space',
            'price' => 23,
            'status' => 'Available',
        ]);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34A Audit Idempotent Event',
            'starts_at' => now()->addDays(40),
            'ends_at' => now()->addDays(41),
            'status' => 'Available',
            'description' => 'audit idempotent',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $booking = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => now()->addDays(40)->toDateString(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Append-only same-value audit rerun check.',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;

        DB::table('bookings')->where('id', $booking->id)->update([
            'vendor_category_id' => null,
            'category_label_snapshot' => null,
        ]);

        Phase34SchemaBackfill::backfillCategoryRelationships();
        $first = CategoryMigrationAudit::query()
            ->where('source_table', 'bookings')
            ->where('source_primary_key', $booking->id)
            ->firstOrFail();

        $immutable = [
            'id' => $first->id,
            'original_value' => $first->original_value,
            'normalized_value' => $first->normalized_value,
            'normalized_value_hash' => $first->normalized_value_hash,
            'mapping_status' => $first->mapping_status,
            'matched_vendor_category_id' => $first->matched_vendor_category_id,
            'reason_code' => $first->reason_code,
            'backfill_version' => $first->backfill_version,
            'created_at' => (string) $first->created_at,
            'updated_at' => (string) $first->updated_at,
        ];

        Phase34SchemaBackfill::backfillCategoryRelationships();

        $audits = CategoryMigrationAudit::query()
            ->where('source_table', 'bookings')
            ->where('source_primary_key', $booking->id)
            ->get();

        $this->assertCount(1, $audits);
        $second = $audits->first();
        $this->assertSame($immutable['id'], $second->id);
        $this->assertSame($immutable['original_value'], $second->original_value);
        $this->assertSame($immutable['normalized_value'], $second->normalized_value);
        $this->assertSame($immutable['normalized_value_hash'], $second->normalized_value_hash);
        $this->assertSame($immutable['mapping_status'], $second->mapping_status);
        $this->assertSame($immutable['matched_vendor_category_id'], $second->matched_vendor_category_id);
        $this->assertSame($immutable['reason_code'], $second->reason_code);
        $this->assertSame($immutable['backfill_version'], $second->backfill_version);
        $this->assertSame($immutable['created_at'], (string) $second->created_at);
        $this->assertSame($immutable['updated_at'], (string) $second->updated_at);
        $this->assertSame(
            CategoryLegacyMapper::normalizedValueHash('Food & Beverages'),
            $second->normalized_value_hash,
        );
    }

    public function test_changed_source_value_preserves_prior_audit_history(): void
    {
        $user = $this->trackUser(User::factory()->community()->create());
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34A Audit History Space',
            'price' => 24,
            'status' => 'Available',
        ]);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34A Audit History Event',
            'starts_at' => now()->addDays(42),
            'ends_at' => now()->addDays(43),
            'status' => 'Available',
            'description' => 'audit history',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $booking = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => now()->addDays(42)->toDateString(),
            'product_category' => 'Food & Drinks',
            'product_details' => 'Unknown then corrected must keep both audit rows.',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;

        DB::table('bookings')->where('id', $booking->id)->update([
            'vendor_category_id' => null,
            'category_label_snapshot' => null,
        ]);

        Phase34SchemaBackfill::backfillCategoryRelationships();

        $unresolved = CategoryMigrationAudit::query()
            ->where('source_table', 'bookings')
            ->where('source_primary_key', $booking->id)
            ->where('normalized_value', 'Food & Drinks')
            ->firstOrFail();

        $this->assertSame(CategoryLegacyMapper::STATUS_UNRESOLVED, $unresolved->mapping_status);
        $unresolvedId = $unresolved->id;
        $unresolvedUpdatedAt = (string) $unresolved->updated_at;

        DB::table('bookings')->where('id', $booking->id)->update([
            'product_category' => 'Food & Beverages',
            'vendor_category_id' => null,
            'category_label_snapshot' => null,
        ]);

        Phase34SchemaBackfill::backfillCategoryRelationships();

        $audits = CategoryMigrationAudit::query()
            ->where('source_table', 'bookings')
            ->where('source_primary_key', $booking->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $audits);
        $this->assertSame('Food & Drinks', $audits[0]->normalized_value);
        $this->assertSame(CategoryLegacyMapper::STATUS_UNRESOLVED, $audits[0]->mapping_status);
        $this->assertSame($unresolvedId, $audits[0]->id);
        $this->assertSame($unresolvedUpdatedAt, (string) $audits[0]->updated_at);

        $this->assertSame('Food & Beverages', $audits[1]->normalized_value);
        $this->assertSame(CategoryLegacyMapper::STATUS_MAPPED, $audits[1]->mapping_status);
        $this->assertSame('Food & Beverages', $booking->fresh()->product_category);
        $this->assertSame('Food & Beverages', $booking->fresh()->category_label_snapshot);
    }

    public function test_new_backfill_version_creates_separate_audit_row(): void
    {
        $user = $this->trackUser(User::factory()->community()->create());
        $space = Space::query()->first() ?? Space::create([
            'space_size' => 'Phase34A Version Space',
            'price' => 25,
            'status' => 'Available',
        ]);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Phase34A Version Event',
            'starts_at' => now()->addDays(44),
            'ends_at' => now()->addDays(45),
            'status' => 'Available',
            'description' => 'versioned audit',
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $booking = Booking::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'carboot_event_id' => $event->id,
            'booking_date' => now()->addDays(44)->toDateString(),
            'product_category' => 'Household Items',
            'product_details' => 'New backfill version must append, not overwrite.',
            'approval_status' => 'Pending_Organizer',
        ]);
        $this->createdBookingIds[] = $booking->id;

        Phase34SchemaBackfill::backfillCategoryRelationships();

        $hash = CategoryLegacyMapper::normalizedValueHash('Household Items');
        $now = now();

        DB::table('category_migration_audits')->insertOrIgnore([
            'source_table' => 'bookings',
            'source_primary_key' => $booking->id,
            'source_column' => 'product_category',
            'original_value' => 'Household Items',
            'normalized_value' => 'Household Items',
            'normalized_value_hash' => $hash,
            'mapping_status' => CategoryLegacyMapper::STATUS_MAPPED,
            'matched_vendor_category_id' => $booking->fresh()->vendor_category_id,
            'reason_code' => CategoryLegacyMapper::REASON_EXACT_MATCH,
            'backfill_version' => 'phase_3_4_v2_test',
            'metadata' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $versions = CategoryMigrationAudit::query()
            ->where('source_table', 'bookings')
            ->where('source_primary_key', $booking->id)
            ->pluck('backfill_version')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['phase_3_4_v1', 'phase_3_4_v2_test'], $versions);
    }

    public function test_null_observation_hash_is_deterministic_and_idempotent(): void
    {
        $user = $this->trackUser(User::factory()->community()->create());

        $profileId = DB::table('vendor_business_profiles')->insertGetId([
            'user_id' => $user->id,
            'business_name' => 'Phase34A Null Category Profile',
            'business_phone' => '0111111111',
            'business_category' => null,
            'description' => 'null category audit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Phase34SchemaBackfill::backfillCategoryRelationships();
        Phase34SchemaBackfill::backfillCategoryRelationships();

        $audits = CategoryMigrationAudit::query()
            ->where('source_table', 'vendor_business_profiles')
            ->where('source_primary_key', $profileId)
            ->get();

        $this->assertCount(1, $audits);
        $this->assertNull($audits->first()->normalized_value);
        $this->assertSame(
            CategoryLegacyMapper::normalizedValueHash(null),
            $audits->first()->normalized_value_hash,
        );
        $this->assertSame(CategoryLegacyMapper::STATUS_SKIPPED_NULL, $audits->first()->mapping_status);

        DB::table('vendor_business_profiles')->where('id', $profileId)->delete();
        CategoryMigrationAudit::query()->where('source_primary_key', $profileId)->where('source_table', 'vendor_business_profiles')->delete();
    }
}
