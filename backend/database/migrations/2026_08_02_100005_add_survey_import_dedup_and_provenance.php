<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey import deduplication, active-batch provenance, and response source fields.
 * Also supersedes exact duplicate completed batches (same event + schema + checksum).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('raw_survey_uploads')) {
            Schema::table('raw_survey_uploads', function (Blueprint $table) {
                if (! Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                    $table->boolean('is_active')->default(false)->after('status');
                }
                if (! Schema::hasColumn('raw_survey_uploads', 'submission_source')) {
                    $table->string('submission_source', 32)->default('csv_import')->after('is_active');
                }
                if (! Schema::hasColumn('raw_survey_uploads', 'duplicate_of_id')) {
                    $table->unsignedBigInteger('duplicate_of_id')->nullable()->after('submission_source');
                }
                if (! Schema::hasColumn('raw_survey_uploads', 'superseded_at')) {
                    $table->timestamp('superseded_at')->nullable()->after('duplicate_of_id');
                }
                if (! Schema::hasColumn('raw_survey_uploads', 'superseded_by_id')) {
                    $table->unsignedBigInteger('superseded_by_id')->nullable()->after('superseded_at');
                }
                if (! Schema::hasColumn('raw_survey_uploads', 'active_dedup_key')) {
                    // Populated only while is_active=true so MySQL unique allows many inactive NULLs.
                    $table->string('active_dedup_key', 191)->nullable()->after('sha256');
                }
            });

            Schema::table('raw_survey_uploads', function (Blueprint $table) {
                if (! $this->hasForeignKey('raw_survey_uploads', 'raw_survey_uploads_duplicate_of_id_foreign')) {
                    $table->foreign('duplicate_of_id')
                        ->references('id')
                        ->on('raw_survey_uploads')
                        ->nullOnDelete();
                }
                if (! $this->hasForeignKey('raw_survey_uploads', 'raw_survey_uploads_superseded_by_id_foreign')) {
                    $table->foreign('superseded_by_id')
                        ->references('id')
                        ->on('raw_survey_uploads')
                        ->nullOnDelete();
                }
            });

            if (! $this->hasIndex('raw_survey_uploads', 'raw_survey_uploads_event_active_dedup_unique')) {
                Schema::table('raw_survey_uploads', function (Blueprint $table) {
                    $table->unique(
                        ['carboot_event_id', 'active_dedup_key'],
                        'raw_survey_uploads_event_active_dedup_unique'
                    );
                });
            }
        }

        if (Schema::hasTable('survey_responses')) {
            Schema::table('survey_responses', function (Blueprint $table) {
                if (! Schema::hasColumn('survey_responses', 'submission_source')) {
                    $table->string('submission_source', 32)->default('csv_import')->after('import_batch_id');
                }
                if (! Schema::hasColumn('survey_responses', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('validation_status');
                }
                if (! Schema::hasColumn('survey_responses', 'vendor_user_id')) {
                    $table->unsignedBigInteger('vendor_user_id')->nullable()->after('submission_source');
                }
                if (! Schema::hasColumn('survey_responses', 'active_system_key')) {
                    // Future: one active system submission per event + vendor + schema.
                    $table->string('active_system_key', 191)->nullable()->after('vendor_user_id');
                }
            });

            // Allow system submissions without an import batch (no doctrine/dbal required).
            if (Schema::hasColumn('survey_responses', 'import_batch_id')) {
                $fk = $this->foreignKeyName('survey_responses', 'import_batch_id');
                if ($fk) {
                    DB::statement("ALTER TABLE survey_responses DROP FOREIGN KEY `{$fk}`");
                }
                DB::statement('ALTER TABLE survey_responses MODIFY import_batch_id BIGINT UNSIGNED NULL');
                Schema::table('survey_responses', function (Blueprint $table) {
                    $table->foreign('import_batch_id')
                        ->references('id')
                        ->on('raw_survey_uploads')
                        ->nullOnDelete();
                });
            }

            Schema::table('survey_responses', function (Blueprint $table) {
                if (! $this->hasForeignKey('survey_responses', 'survey_responses_vendor_user_id_foreign')) {
                    $table->foreign('vendor_user_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
                if (! $this->hasIndex('survey_responses', 'survey_responses_event_active_valid_index')) {
                    $table->index(
                        ['carboot_event_id', 'is_active', 'validation_status'],
                        'survey_responses_event_active_valid_index'
                    );
                }
                if (! $this->hasIndex('survey_responses', 'survey_responses_event_active_system_unique')) {
                    $table->unique(
                        ['carboot_event_id', 'active_system_key'],
                        'survey_responses_event_active_system_unique'
                    );
                }
            });
        }

        $this->backfillAndDeduplicate();
    }

    public function down(): void
    {
        if (Schema::hasTable('survey_responses')) {
            Schema::table('survey_responses', function (Blueprint $table) {
                if ($this->hasIndex('survey_responses', 'survey_responses_event_active_system_unique')) {
                    $table->dropUnique('survey_responses_event_active_system_unique');
                }
                if ($this->hasIndex('survey_responses', 'survey_responses_event_active_valid_index')) {
                    $table->dropIndex('survey_responses_event_active_valid_index');
                }
                if ($this->hasForeignKey('survey_responses', 'survey_responses_vendor_user_id_foreign')) {
                    $table->dropForeign(['vendor_user_id']);
                }
            });

            Schema::table('survey_responses', function (Blueprint $table) {
                if (Schema::hasColumn('survey_responses', 'active_system_key')) {
                    $table->dropColumn('active_system_key');
                }
                if (Schema::hasColumn('survey_responses', 'vendor_user_id')) {
                    $table->dropColumn('vendor_user_id');
                }
                if (Schema::hasColumn('survey_responses', 'is_active')) {
                    $table->dropColumn('is_active');
                }
                if (Schema::hasColumn('survey_responses', 'submission_source')) {
                    $table->dropColumn('submission_source');
                }
            });
        }

        if (Schema::hasTable('raw_survey_uploads')) {
            Schema::table('raw_survey_uploads', function (Blueprint $table) {
                if ($this->hasIndex('raw_survey_uploads', 'raw_survey_uploads_event_active_dedup_unique')) {
                    $table->dropUnique('raw_survey_uploads_event_active_dedup_unique');
                }
                if ($this->hasForeignKey('raw_survey_uploads', 'raw_survey_uploads_duplicate_of_id_foreign')) {
                    $table->dropForeign(['duplicate_of_id']);
                }
                if ($this->hasForeignKey('raw_survey_uploads', 'raw_survey_uploads_superseded_by_id_foreign')) {
                    $table->dropForeign(['superseded_by_id']);
                }
            });

            Schema::table('raw_survey_uploads', function (Blueprint $table) {
                foreach (['active_dedup_key', 'superseded_by_id', 'superseded_at', 'duplicate_of_id', 'submission_source', 'is_active'] as $column) {
                    if (Schema::hasColumn('raw_survey_uploads', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function backfillAndDeduplicate(): void
    {
        if (! Schema::hasTable('raw_survey_uploads')) {
            return;
        }

        DB::transaction(function () {
            $batches = DB::table('raw_survey_uploads')
                ->orderBy('carboot_event_id')
                ->orderBy('schema_name')
                ->orderBy('schema_version')
                ->orderBy('sha256')
                ->orderBy('id')
                ->get();

            $groups = [];
            foreach ($batches as $batch) {
                $key = implode('|', [
                    $batch->carboot_event_id,
                    $batch->schema_name,
                    $batch->schema_version,
                    $batch->sha256,
                ]);
                $groups[$key][] = $batch;
            }

            $keptActiveIds = [];

            foreach ($groups as $group) {
                $completed = array_values(array_filter(
                    $group,
                    fn ($b) => in_array($b->status, ['completed', 'completed_with_errors'], true)
                ));

                if ($completed === []) {
                    foreach ($group as $batch) {
                        DB::table('raw_survey_uploads')->where('id', $batch->id)->update([
                            'is_active' => false,
                            'submission_source' => 'csv_import',
                            'active_dedup_key' => null,
                            'updated_at' => now(),
                        ]);
                    }
                    continue;
                }

                $keeper = $completed[0];
                $keptActiveIds[] = (int) $keeper->id;

                DB::table('raw_survey_uploads')->where('id', $keeper->id)->update([
                    'is_active' => true,
                    'submission_source' => 'csv_import',
                    'duplicate_of_id' => null,
                    'superseded_at' => null,
                    'superseded_by_id' => null,
                    'active_dedup_key' => $this->dedupKey($keeper->schema_name, $keeper->schema_version, $keeper->sha256),
                    'updated_at' => now(),
                ]);

                foreach (array_slice($completed, 1) as $duplicate) {
                    DB::table('raw_survey_uploads')->where('id', $duplicate->id)->update([
                        'status' => 'duplicate',
                        'is_active' => false,
                        'submission_source' => 'csv_import',
                        'duplicate_of_id' => $keeper->id,
                        'active_dedup_key' => null,
                        'updated_at' => now(),
                    ]);

                    if (Schema::hasTable('survey_responses')) {
                        DB::table('survey_responses')
                            ->where('import_batch_id', $duplicate->id)
                            ->delete();
                    }
                }

                foreach ($group as $batch) {
                    if (in_array($batch->status, ['completed', 'completed_with_errors', 'duplicate'], true)) {
                        continue;
                    }
                    DB::table('raw_survey_uploads')->where('id', $batch->id)->update([
                        'is_active' => false,
                        'submission_source' => 'csv_import',
                        'active_dedup_key' => null,
                        'updated_at' => now(),
                    ]);
                }
            }

            // One active CSV dataset per event + schema version (different checksums).
            $activeBySchema = [];
            foreach ($keptActiveIds as $id) {
                $batch = DB::table('raw_survey_uploads')->where('id', $id)->first();
                if (! $batch) {
                    continue;
                }
                $schemaKey = implode('|', [$batch->carboot_event_id, $batch->schema_name, $batch->schema_version]);
                if (! isset($activeBySchema[$schemaKey])) {
                    $activeBySchema[$schemaKey] = $batch;
                    continue;
                }

                $existing = $activeBySchema[$schemaKey];
                // Keep earliest completed batch as the single active dataset.
                $keep = ((int) $existing->id <= (int) $batch->id) ? $existing : $batch;
                $drop = $keep->id === $existing->id ? $batch : $existing;
                $activeBySchema[$schemaKey] = $keep;

                DB::table('raw_survey_uploads')->where('id', $keep->id)->update([
                    'is_active' => true,
                    'active_dedup_key' => $this->dedupKey($keep->schema_name, $keep->schema_version, $keep->sha256),
                    'updated_at' => now(),
                ]);
                DB::table('raw_survey_uploads')->where('id', $drop->id)->update([
                    'status' => 'superseded',
                    'is_active' => false,
                    'superseded_at' => now(),
                    'superseded_by_id' => $keep->id,
                    'active_dedup_key' => null,
                    'updated_at' => now(),
                ]);
                if (Schema::hasTable('survey_responses')) {
                    DB::table('survey_responses')
                        ->where('import_batch_id', $drop->id)
                        ->update(['is_active' => false, 'updated_at' => now()]);
                }
            }

            if (Schema::hasTable('survey_responses')) {
                DB::table('survey_responses')->update([
                    'submission_source' => DB::raw("COALESCE(submission_source, 'csv_import')"),
                ]);

                $activeBatchIds = DB::table('raw_survey_uploads')
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all();

                DB::table('survey_responses')
                    ->whereNotNull('import_batch_id')
                    ->whereIn('import_batch_id', $activeBatchIds ?: [0])
                    ->update(['is_active' => true, 'updated_at' => now()]);

                DB::table('survey_responses')
                    ->whereNotNull('import_batch_id')
                    ->whereNotIn('import_batch_id', $activeBatchIds ?: [0])
                    ->update(['is_active' => false, 'updated_at' => now()]);
            }

            if (Schema::hasTable('analytics_results')) {
                DB::table('analytics_results')
                    ->where('metric_key', 'vendor_survey')
                    ->delete();
            }
        });
    }

    private function dedupKey(string $schemaName, string $schemaVersion, string $sha256): string
    {
        return $schemaName.'|'.$schemaVersion.'|'.$sha256;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.table_constraints
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            [$database, $table, $constraintName, 'FOREIGN KEY']
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }

    private function foreignKeyName(string $table, string $column): ?string
    {
        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$database, $table, $column]
        );

        return $row->name ?? null;
    }
};
