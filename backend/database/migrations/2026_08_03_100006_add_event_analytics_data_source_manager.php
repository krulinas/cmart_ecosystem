<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event-level analytics source mode + CSV batch soft lifecycle fields for Data Source Manager.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('carboot_events') && ! Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
            Schema::table('carboot_events', function (Blueprint $table) {
                $table->string('analytics_source_mode', 32)
                    ->default('combined')
                    ->after('status');
            });
        }

        if (Schema::hasTable('raw_survey_uploads')) {
            Schema::table('raw_survey_uploads', function (Blueprint $table) {
                if (! Schema::hasColumn('raw_survey_uploads', 'excluded_at')) {
                    $table->timestamp('excluded_at')->nullable()->after('superseded_by_id');
                }
                if (! Schema::hasColumn('raw_survey_uploads', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable()->after('excluded_at');
                }
                if (! Schema::hasColumn('raw_survey_uploads', 'restored_from_status')) {
                    $table->string('restored_from_status', 32)->nullable()->after('archived_at');
                }
            });

            // Ensure at most one active batch per event+schema when provenance columns exist.
            if (Schema::hasColumn('raw_survey_uploads', 'is_active')) {
                $this->ensureSingleActiveBatchPerSchema();
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('raw_survey_uploads')) {
            Schema::table('raw_survey_uploads', function (Blueprint $table) {
                foreach (['restored_from_status', 'archived_at', 'excluded_at'] as $column) {
                    if (Schema::hasColumn('raw_survey_uploads', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('carboot_events') && Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
            Schema::table('carboot_events', function (Blueprint $table) {
                $table->dropColumn('analytics_source_mode');
            });
        }
    }

    private function ensureSingleActiveBatchPerSchema(): void
    {
        $active = DB::table('raw_survey_uploads')
            ->where('is_active', true)
            ->orderBy('carboot_event_id')
            ->orderBy('schema_name')
            ->orderBy('schema_version')
            ->orderBy('id')
            ->get();

        $seen = [];
        foreach ($active as $batch) {
            $key = implode('|', [$batch->carboot_event_id, $batch->schema_name, $batch->schema_version]);
            if (! isset($seen[$key])) {
                $seen[$key] = (int) $batch->id;
                continue;
            }

            DB::table('raw_survey_uploads')->where('id', $batch->id)->update([
                'is_active' => false,
                'status' => 'duplicate',
                'duplicate_of_id' => $seen[$key],
                'active_dedup_key' => null,
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('survey_responses') && Schema::hasColumn('survey_responses', 'is_active')) {
                DB::table('survey_responses')
                    ->where('import_batch_id', $batch->id)
                    ->update(['is_active' => false, 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('analytics_results')) {
            DB::table('analytics_results')
                ->where('metric_key', 'vendor_survey')
                ->delete();
        }
    }
};
