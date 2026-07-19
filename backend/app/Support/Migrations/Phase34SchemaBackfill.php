<?php

namespace App\Support\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 3.4 — backfill helpers for category FKs and event layout rows.
 */
class Phase34SchemaBackfill
{
    /**
     * Idempotently insert the seven canonical vendor categories.
     *
     * @return int number of rows inserted this run
     */
    public static function seedCanonicalCategories(): int
    {
        $inserted = 0;
        $now = now();

        foreach (CategoryLegacyMapper::canonicalCategories() as $category) {
            $exists = DB::table('vendor_categories')
                ->where('slug', $category['slug'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('vendor_categories')->insert([
                'slug' => $category['slug'],
                'label' => $category['label'],
                'description' => $category['description'],
                'display_order' => $category['display_order'],
                'is_active' => $category['is_active'],
                'is_public' => $category['is_public'],
                'archived_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * @return array{exact: int, alias: int, unresolved: int, skipped_null: int, snapshots: int}
     */
    public static function backfillCategoryRelationships(): array
    {
        $labelToId = DB::table('vendor_categories')
            ->pluck('id', 'label')
            ->mapWithKeys(fn ($id, $label) => [(string) $label => (int) $id])
            ->all();

        $stats = [
            'exact' => 0,
            'alias' => 0,
            'unresolved' => 0,
            'skipped_null' => 0,
            'snapshots' => 0,
        ];

        $sources = [
            [
                'table' => 'bookings',
                'column' => 'product_category',
                'required' => true,
                'set_snapshot' => true,
            ],
            [
                'table' => 'vendor_business_profiles',
                'column' => 'business_category',
                'required' => false,
                'set_snapshot' => false,
            ],
            [
                'table' => 'user_booking_preferences',
                'column' => 'product_category',
                'required' => false,
                'set_snapshot' => false,
            ],
            [
                'table' => 'vendor_items',
                'column' => 'category',
                'required' => true,
                'set_snapshot' => false,
            ],
        ];

        foreach ($sources as $source) {
            if (! Schema::hasTable($source['table']) || ! Schema::hasColumn($source['table'], $source['column'])) {
                continue;
            }

            $rows = DB::table($source['table'])->orderBy('id')->get(['id', $source['column'], 'vendor_category_id']);

            foreach ($rows as $row) {
                $original = $row->{$source['column']};
                $resolved = CategoryLegacyMapper::resolve(
                    $original === null ? null : (string) $original,
                    $labelToId,
                    $source['required'],
                );

                self::writeAudit(
                    $source['table'],
                    (int) $row->id,
                    $source['column'],
                    $original === null ? null : (string) $original,
                    $resolved,
                );

                match ($resolved['mapping_status']) {
                    CategoryLegacyMapper::STATUS_MAPPED => $resolved['reason_code'] === CategoryLegacyMapper::REASON_APPROVED_ALIAS
                        ? $stats['alias']++
                        : $stats['exact']++,
                    CategoryLegacyMapper::STATUS_UNRESOLVED => $stats['unresolved']++,
                    default => $stats['skipped_null']++,
                };

                if ($resolved['matched_vendor_category_id'] === null) {
                    continue;
                }

                if ($row->vendor_category_id === null) {
                    $update = [
                        'vendor_category_id' => $resolved['matched_vendor_category_id'],
                        'updated_at' => now(),
                    ];

                    if ($source['set_snapshot'] && Schema::hasColumn($source['table'], 'category_label_snapshot')) {
                        $update['category_label_snapshot'] = $resolved['matched_label'];
                        $stats['snapshots']++;
                    }

                    DB::table($source['table'])->where('id', $row->id)->update($update);
                } elseif (
                    $source['set_snapshot']
                    && Schema::hasColumn($source['table'], 'category_label_snapshot')
                ) {
                    $currentSnapshot = DB::table($source['table'])->where('id', $row->id)->value('category_label_snapshot');
                    if ($currentSnapshot === null && $resolved['matched_label'] !== null) {
                        DB::table($source['table'])->where('id', $row->id)->update([
                            'category_label_snapshot' => $resolved['matched_label'],
                            'updated_at' => now(),
                        ]);
                        $stats['snapshots']++;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Append-only audit insert. Same source + version + normalized identity is
     * ignored; changed values create a new immutable observation.
     *
     * @param  array{
     *   mapping_status: string,
     *   reason_code: string,
     *   normalized_value: string|null,
     *   matched_vendor_category_id: int|null,
     *   matched_label: string|null
     * }  $resolved
     */
    public static function writeAudit(
        string $sourceTable,
        int $sourcePrimaryKey,
        string $sourceColumn,
        ?string $originalValue,
        array $resolved,
    ): void {
        $normalized = $resolved['normalized_value'];
        $hash = CategoryLegacyMapper::normalizedValueHash($normalized);
        $now = now();
        $appendOnlySchema = Schema::hasColumn(
            'category_migration_audits',
            'normalized_value_hash',
        );

        $observation = [
            'source_table' => $sourceTable,
            'source_primary_key' => $sourcePrimaryKey,
            'source_column' => $sourceColumn,
            'original_value' => $originalValue,
            'normalized_value' => $normalized,
            'mapping_status' => $resolved['mapping_status'],
            'matched_vendor_category_id' => $resolved['matched_vendor_category_id'],
            'reason_code' => $resolved['reason_code'],
            'backfill_version' => CategoryLegacyMapper::BACKFILL_VERSION,
            'metadata' => json_encode([
                'matched_label' => $resolved['matched_label'],
                'backfill_version' => CategoryLegacyMapper::BACKFILL_VERSION,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($appendOnlySchema) {
            $observation['normalized_value_hash'] = $hash;
        }

        try {
            DB::table('category_migration_audits')->insert($observation);
        } catch (QueryException $exception) {
            if (! self::isDuplicateKeyViolation($exception)) {
                throw $exception;
            }

            $existingQuery = DB::table('category_migration_audits')
                ->where('source_table', $sourceTable)
                ->where('source_primary_key', $sourcePrimaryKey)
                ->where('source_column', $sourceColumn)
                ->where('backfill_version', CategoryLegacyMapper::BACKFILL_VERSION);

            if ($appendOnlySchema) {
                $existingQuery->where('normalized_value_hash', $hash);
            }

            if (! $existingQuery->exists()) {
                throw $exception;
            }
        }
    }

    private static function isDuplicateKeyViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * @return array{
     *   sites_inspected: int,
     *   groups: int,
     *   rows_created: int,
     *   sites_linked: int,
     *   unresolved_sites: int,
     *   slug_collisions: int
     * }
     */
    public static function backfillEventLayoutRows(): array
    {
        $stats = [
            'sites_inspected' => 0,
            'groups' => 0,
            'rows_created' => 0,
            'sites_linked' => 0,
            'unresolved_sites' => 0,
            'slug_collisions' => 0,
        ];

        if (! Schema::hasTable('event_sites') || ! Schema::hasTable('event_layout_rows')) {
            return $stats;
        }

        $sites = DB::table('event_sites')
            ->orderBy('carboot_event_id')
            ->orderBy('display_order')
            ->orderBy('grid_row')
            ->orderBy('grid_column')
            ->orderBy('id')
            ->get();

        $stats['sites_inspected'] = $sites->count();

        /** @var array<string, array{event_id: int, label: string, site_ids: list<int>, display_order: int}> $groups */
        $groups = [];

        foreach ($sites as $site) {
            $normalizedLabel = CategoryLegacyMapper::normalize((string) ($site->row_label ?? ''));

            if ($normalizedLabel === null || $normalizedLabel === '') {
                $stats['unresolved_sites']++;
                continue;
            }

            $eventId = (int) $site->carboot_event_id;
            $key = $eventId.'|'.$normalizedLabel;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'event_id' => $eventId,
                    'label' => $normalizedLabel,
                    'site_ids' => [],
                    'display_order' => (int) $site->display_order,
                    'grid_row' => (int) $site->grid_row,
                ];
            }

            $groups[$key]['site_ids'][] = (int) $site->id;
            $groups[$key]['display_order'] = min($groups[$key]['display_order'], (int) $site->display_order);
            $groups[$key]['grid_row'] = min($groups[$key]['grid_row'], (int) $site->grid_row);
        }

        $stats['groups'] = count($groups);

        // Deterministic order within each event: min display_order, then min grid_row, then label.
        $byEvent = [];
        foreach ($groups as $group) {
            $byEvent[$group['event_id']][] = $group;
        }

        foreach ($byEvent as $eventId => $eventGroups) {
            usort($eventGroups, function (array $a, array $b): int {
                return [$a['display_order'], $a['grid_row'], $a['label']]
                    <=> [$b['display_order'], $b['grid_row'], $b['label']];
            });

            $usedSlugs = DB::table('event_layout_rows')
                ->where('carboot_event_id', $eventId)
                ->pluck('slug')
                ->map(fn ($s) => (string) $s)
                ->all();
            $usedSlugs = array_fill_keys($usedSlugs, true);

            $order = 0;
            foreach ($eventGroups as $group) {
                $order++;
                $existing = DB::table('event_layout_rows')
                    ->where('carboot_event_id', $eventId)
                    ->where('label', $group['label'])
                    ->first();

                if ($existing) {
                    $rowId = (int) $existing->id;
                } else {
                    [$slug, $collision] = self::uniqueSlug($group['label'], $usedSlugs);
                    if ($collision) {
                        $stats['slug_collisions']++;
                    }
                    $usedSlugs[$slug] = true;

                    $rowId = (int) DB::table('event_layout_rows')->insertGetId([
                        'carboot_event_id' => $eventId,
                        'vendor_category_id' => null,
                        'label' => $group['label'],
                        'slug' => $slug,
                        'description' => null,
                        'display_order' => $order,
                        'is_active' => true,
                        'is_public' => true,
                        'created_by' => null,
                        'updated_by' => null,
                        'archived_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stats['rows_created']++;
                }

                $linked = DB::table('event_sites')
                    ->whereIn('id', $group['site_ids'])
                    ->whereNull('event_layout_row_id')
                    ->update([
                        'event_layout_row_id' => $rowId,
                        'updated_at' => now(),
                    ]);

                $stats['sites_linked'] += $linked;
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, true>  $usedSlugs
     * @return array{0: string, 1: bool}
     */
    public static function uniqueSlug(string $label, array $usedSlugs): array
    {
        $base = Str::slug($label);
        if ($base === '') {
            $base = 'row';
        }

        $slug = $base;
        $suffix = 2;
        $collision = false;

        while (isset($usedSlugs[$slug])) {
            $collision = true;
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return [$slug, $collision];
    }
}
