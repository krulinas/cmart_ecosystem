<?php

use App\Support\Migrations\CategoryLegacyMapper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.4A — genuine append-only category migration audits.
 *
 * Adds normalized_value_hash and widens uniqueness so changed source values
 * create new immutable observations instead of overwriting history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('category_migration_audits', 'normalized_value_hash')) {
            Schema::table('category_migration_audits', function (Blueprint $table) {
                $table->char('normalized_value_hash', 64)
                    ->nullable()
                    ->after('normalized_value');
            });
        }

        $rows = DB::table('category_migration_audits')
            ->whereNull('normalized_value_hash')
            ->orderBy('id')
            ->get(['id', 'normalized_value']);
        foreach ($rows as $row) {
            DB::table('category_migration_audits')->where('id', $row->id)->update([
                'normalized_value_hash' => CategoryLegacyMapper::normalizedValueHash(
                    $row->normalized_value === null ? null : (string) $row->normalized_value,
                ),
            ]);
        }

        $this->dropIndexIfExists('category_migration_audits', 'category_migration_audits_source_unique');
        $this->dropIndexIfExists('category_migration_audits', 'category_migration_audits_append_only_unique');

        // MySQL cannot alter nullable→NOT NULL and unique in one step safely after backfill.
        DB::statement('ALTER TABLE category_migration_audits MODIFY normalized_value_hash CHAR(64) NOT NULL');

        Schema::table('category_migration_audits', function (Blueprint $table) {
            $table->unique(
                [
                    'source_table',
                    'source_primary_key',
                    'source_column',
                    'backfill_version',
                    'normalized_value_hash',
                ],
                'category_migration_audits_append_only_unique',
            );
        });
    }

    public function down(): void
    {
        $collision = DB::table('category_migration_audits')
            ->select('source_table', 'source_primary_key', 'source_column', 'backfill_version')
            ->groupBy('source_table', 'source_primary_key', 'source_column', 'backfill_version')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($collision !== null) {
            throw new RuntimeException(
                'Phase 3.4A rollback refused: append-only category audit observations '
                .'would collide under the legacy unique key. No schema or audit data was changed.',
            );
        }

        $this->dropIndexIfExists('category_migration_audits', 'category_migration_audits_append_only_unique');

        if (Schema::hasColumn('category_migration_audits', 'normalized_value_hash')) {
            Schema::table('category_migration_audits', function (Blueprint $table) {
                $table->dropColumn('normalized_value_hash');
            });
        }

        $this->dropIndexIfExists('category_migration_audits', 'category_migration_audits_source_unique');

        Schema::table('category_migration_audits', function (Blueprint $table) {
            $table->unique(
                ['source_table', 'source_primary_key', 'source_column', 'backfill_version'],
                'category_migration_audits_source_unique',
            );
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropUnique($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
