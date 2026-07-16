<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.4A — prevent hard-delete of layout rows that still have sites.
 *
 * Replaces nullOnDelete with restrictOnDelete on event_sites.event_layout_row_id.
 * Column remains nullable for Phase 3.4 compatibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sites', function (Blueprint $table) {
            $table->dropForeign(['event_layout_row_id']);
        });

        Schema::table('event_sites', function (Blueprint $table) {
            $table->foreign('event_layout_row_id', 'event_sites_event_layout_row_id_foreign')
                ->references('id')
                ->on('event_layout_rows')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_sites', function (Blueprint $table) {
            $table->dropForeign('event_sites_event_layout_row_id_foreign');
        });

        Schema::table('event_sites', function (Blueprint $table) {
            $table->foreign('event_layout_row_id', 'event_sites_event_layout_row_id_foreign')
                ->references('id')
                ->on('event_layout_rows')
                ->nullOnDelete();
        });
    }
};
