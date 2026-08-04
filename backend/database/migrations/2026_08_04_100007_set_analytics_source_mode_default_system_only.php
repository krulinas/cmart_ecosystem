<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lock Analytics Hub defaults: new events use system_only.
 * Soft lifecycle columns are retained but unused by organizer UX.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carboot_events') || ! Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
            return;
        }

        // Change column default for new rows only — do not rewrite existing event modes.
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE carboot_events MODIFY analytics_source_mode VARCHAR(32) NOT NULL DEFAULT 'system_only'"
            );
        } else {
            Schema::table('carboot_events', function (Blueprint $table) {
                $table->string('analytics_source_mode', 32)->default('system_only')->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('carboot_events') || ! Schema::hasColumn('carboot_events', 'analytics_source_mode')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE carboot_events MODIFY analytics_source_mode VARCHAR(32) NOT NULL DEFAULT 'combined'"
            );
        } else {
            Schema::table('carboot_events', function (Blueprint $table) {
                $table->string('analytics_source_mode', 32)->default('combined')->change();
            });
        }
    }
};
