<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.4 — nullable event_sites → event_layout_rows link.
 *
 * row_label remains the Phase 2 write-path source until Phase 3.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sites', function (Blueprint $table) {
            // nullOnDelete keeps carboot_events cascade deletes safe when rows and
            // sites are both children of the same event. Organizer hard-delete of
            // rows with linked sites is blocked later by EventLayoutLockService.
            $table->foreignId('event_layout_row_id')
                ->nullable()
                ->after('carboot_event_id')
                ->constrained('event_layout_rows')
                ->nullOnDelete();
            $table->index('event_layout_row_id', 'event_sites_event_layout_row_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('event_sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_layout_row_id');
        });
    }
};
