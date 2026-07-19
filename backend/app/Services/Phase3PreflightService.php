<?php

namespace App\Services;

use App\Models\BookingCategoryOverride;
use App\Models\CarbootEvent;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.11 — read-only migration and data-integrity preflight.
 */
class Phase3PreflightService
{
    private const EXPECTED_TRIGGER = 'cmart_before_delete_carboot_event_layout';

    public function __construct(
        private readonly EventLayoutReadinessService $readiness,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();
        $version = $driver === 'mysql'
            ? (string) ($connection->selectOne('SELECT VERSION() AS version')->version ?? '')
            : '';
        $schemaReady = $this->phase3SchemaReady();
        $trigger = $this->triggerStatus($connection, $database, $driver);
        $invariants = $schemaReady ? $this->integrityCounts() : $this->unavailableIntegrityCounts();
        $pending = $this->pendingMigrations();

        return [
            'environment' => (string) app()->environment(),
            'database' => $database,
            'driver' => $driver,
            'database_version' => $version,
            'database_family' => stripos($version, 'mariadb') !== false ? 'MariaDB' : 'MySQL',
            'read_only' => true,
            'schema_ready' => $schemaReady,
            'migrations' => [
                'repository_exists' => Schema::hasTable('migrations'),
                'pending_count' => count($pending),
                'pending' => $pending,
            ],
            'trigger' => $trigger,
            'canonical_category_count' => Schema::hasTable('vendor_categories')
                ? DB::table('vendor_categories')->count()
                : null,
            'unknown_category_audit_count' => Schema::hasTable('category_migration_audits')
                ? DB::table('category_migration_audits')
                    ->where('mapping_status', 'unresolved')
                    ->count()
                : null,
            'invariants' => $invariants,
            'total_integrity_violations' => collect($invariants)
                ->filter(fn ($value) => is_int($value))
                ->sum(),
            'development_rollout_readiness' => $schemaReady
                && count($pending) === 0
                && $trigger['definition_verified'] === true
                && collect($invariants)->every(fn ($value) => $value === 0),
            'development_rollout_prepared_not_executed' => $database === 'cmart_db',
        ];
    }

    private function phase3SchemaReady(): bool
    {
        foreach ([
            'vendor_categories',
            'category_migration_audits',
            'event_layout_rows',
            'event_sites',
            'booking_category_overrides',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return Schema::hasColumn('bookings', 'vendor_category_id')
            && Schema::hasColumn('bookings', 'category_label_snapshot')
            && Schema::hasColumn('carboot_events', 'public_layout_published_at');
    }

    /**
     * @return array<string, int>
     */
    private function integrityCounts(): array
    {
        $recognized = DB::table('vendor_categories')->pluck('label')->all();
        $recognized[] = 'Others';

        $counts = [
            'canonical_fk_null_recognized_legacy' => 0,
            'canonical_fk_null_unknown_legacy' => 0,
            'fk_legacy_string_mismatch' => 0,
        ];

        foreach ([
            ['bookings', 'product_category'],
            ['vendor_business_profiles', 'business_category'],
            ['user_booking_preferences', 'product_category'],
            ['vendor_items', 'category'],
        ] as [$table, $column]) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'vendor_category_id')
                || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $counts['canonical_fk_null_recognized_legacy'] += DB::table($table)
                ->whereNull('vendor_category_id')
                ->whereIn($column, $recognized)
                ->count();
            $counts['canonical_fk_null_unknown_legacy'] += DB::table($table)
                ->whereNull('vendor_category_id')
                ->whereNotNull($column)
                ->whereRaw("TRIM({$column}) <> ''")
                ->whereNotIn($column, $recognized)
                ->count();
            $counts['fk_legacy_string_mismatch'] += DB::table("{$table} as source")
                ->join('vendor_categories as category', 'category.id', '=', 'source.vendor_category_id')
                ->whereNotNull("source.{$column}")
                ->whereRaw(
                    "source.{$column} <> category.label "
                    ."AND NOT (source.{$column} = 'Others' AND category.slug = 'mixed-others')",
                )
                ->count();
        }

        $counts['booking_snapshot_missing'] = DB::table('bookings')
            ->whereNotNull('vendor_category_id')
            ->where(fn ($query) => $query
                ->whereNull('category_label_snapshot')
                ->orWhereRaw("TRIM(category_label_snapshot) = ''"))
            ->count();
        $counts['booking_snapshot_inconsistent'] = DB::table('bookings as booking')
            ->join('vendor_categories as category', 'category.id', '=', 'booking.vendor_category_id')
            ->whereNotNull('booking.category_label_snapshot')
            ->whereColumn('booking.category_label_snapshot', '!=', 'category.label')
            ->count();
        $counts['active_site_missing_row'] = DB::table('event_sites')
            ->where('operational_status', 'active')
            ->whereNull('event_layout_row_id')
            ->count();
        $counts['site_row_event_mismatch'] = DB::table('event_sites as site')
            ->join('event_layout_rows as row', 'row.id', '=', 'site.event_layout_row_id')
            ->whereColumn('site.carboot_event_id', '!=', 'row.carboot_event_id')
            ->count();
        $counts['active_row_missing_category'] = DB::table('event_layout_rows')
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->whereNull('vendor_category_id')
            ->count();
        $counts['inactive_category_used_operationally'] = DB::table('event_layout_rows as row')
            ->join('vendor_categories as category', 'category.id', '=', 'row.vendor_category_id')
            ->where('row.is_active', true)
            ->whereNull('row.archived_at')
            ->where(fn ($query) => $query
                ->where('category.is_active', false)
                ->orWhereNotNull('category.archived_at'))
            ->count();
        $counts['published_layout_not_public_ready'] = $this->publishedLayoutsNotReady();
        $counts['active_override_inconsistent_placement'] = $this->activeOverrideInconsistencies();
        $counts['multiple_active_overrides'] = DB::table('booking_category_overrides')
            ->where('status', BookingCategoryOverride::STATUS_ACTIVE)
            ->where('active_lock', 1)
            ->select('booking_id')
            ->groupBy('booking_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $counts['active_allocation_with_release_metadata'] = DB::table('booking_day_allocations')
            ->whereIn('allocation_status', ['reserved', 'confirmed'])
            ->where(fn ($query) => $query
                ->whereNotNull('released_at')
                ->orWhereNotNull('released_by')
                ->orWhereNotNull('release_reason'))
            ->count();
        $counts['released_allocation_with_active_lock'] = DB::table('booking_day_allocations')
            ->where('allocation_status', 'released')
            ->where('active_lock', 1)
            ->count();

        return $counts;
    }

    private function publishedLayoutsNotReady(): int
    {
        return CarbootEvent::query()
            ->whereNotNull('public_layout_published_at')
            ->get()
            ->filter(fn (CarbootEvent $event) => ! $this->readiness->assess($event)['public_ready'])
            ->count();
    }

    private function activeOverrideInconsistencies(): int
    {
        return DB::table('booking_category_overrides as override')
            ->where('override.status', BookingCategoryOverride::STATUS_ACTIVE)
            ->where('override.active_lock', 1)
            ->get()
            ->filter(function ($override) {
                $currentCategoryIds = DB::table('booking_day_allocations as allocation')
                    ->join('event_sites as site', 'site.id', '=', 'allocation.event_site_id')
                    ->join('event_layout_rows as row', 'row.id', '=', 'site.event_layout_row_id')
                    ->where('allocation.booking_id', $override->booking_id)
                    ->whereIn('allocation.allocation_status', ['reserved', 'confirmed'])
                    ->where('allocation.active_lock', 1)
                    ->pluck('row.vendor_category_id')
                    ->filter()
                    ->unique()
                    ->values();

                return $currentCategoryIds->count() !== 1
                    || (int) $currentCategoryIds->first() !== (int) $override->assigned_category_id_snapshot
                    || (int) $currentCategoryIds->first() === (int) $override->booking_category_id_snapshot;
            })
            ->count();
    }

    /**
     * @return array<string, int|null>
     */
    private function unavailableIntegrityCounts(): array
    {
        return collect([
            'canonical_fk_null_recognized_legacy',
            'canonical_fk_null_unknown_legacy',
            'fk_legacy_string_mismatch',
            'booking_snapshot_missing',
            'booking_snapshot_inconsistent',
            'active_site_missing_row',
            'site_row_event_mismatch',
            'active_row_missing_category',
            'inactive_category_used_operationally',
            'published_layout_not_public_ready',
            'active_override_inconsistent_placement',
            'multiple_active_overrides',
            'active_allocation_with_release_metadata',
            'released_allocation_with_active_lock',
        ])->mapWithKeys(fn ($key) => [$key => null])->all();
    }

    /**
     * @return list<string>
     */
    private function pendingMigrations(): array
    {
        if (! Schema::hasTable('migrations')) {
            return array_keys(app('migrator')->getMigrationFiles(database_path('migrations')));
        }

        $files = app('migrator')->getMigrationFiles(database_path('migrations'));
        $ran = app('migrator')->getRepository()->getRan();

        return array_values(array_diff(array_keys($files), $ran));
    }

    /**
     * @return array<string, mixed>
     */
    private function triggerStatus(
        ConnectionInterface $connection,
        string $database,
        string $driver,
    ): array {
        if ($driver !== 'mysql') {
            return [
                'name' => self::EXPECTED_TRIGGER,
                'present' => false,
                'definition_verified' => false,
                'create_trigger_privilege' => false,
                'grants_checked' => false,
            ];
        }

        $trigger = $connection->table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', $database)
            ->where('TRIGGER_NAME', self::EXPECTED_TRIGGER)
            ->first(['ACTION_STATEMENT']);
        $definition = strtolower((string) ($trigger->ACTION_STATEMENT ?? ''));
        $grants = collect($connection->select('SHOW GRANTS FOR CURRENT_USER'))
            ->flatMap(fn ($row) => array_values((array) $row))
            ->map(fn ($grant) => strtoupper((string) $grant));
        $hasPrivilege = $grants->contains(fn ($grant) =>
            str_contains($grant, 'ALL PRIVILEGES')
            || str_contains($grant, ' TRIGGER ')
            || str_contains($grant, 'TRIGGER,'));

        return [
            'name' => self::EXPECTED_TRIGGER,
            'present' => $trigger !== null,
            'definition_verified' => str_contains($definition, 'delete from event_sites')
                && str_contains($definition, 'delete from event_layout_rows'),
            'create_trigger_privilege' => $hasPrivilege,
            'grants_checked' => true,
        ];
    }
}
