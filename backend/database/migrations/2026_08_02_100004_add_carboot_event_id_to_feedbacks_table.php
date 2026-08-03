<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional event linkage for community feedback (Track A).
 * Nullable — historical rows remain unscoped until backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedbacks')) {
            return;
        }

        if (Schema::hasColumn('feedbacks', 'carboot_event_id')) {
            return;
        }

        Schema::table('feedbacks', function (Blueprint $table) {
            $table->foreignId('carboot_event_id')
                ->nullable()
                ->after('user_id')
                ->constrained('carboot_events')
                ->nullOnDelete();
            $table->index('carboot_event_id', 'feedbacks_carboot_event_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('feedbacks') || ! Schema::hasColumn('feedbacks', 'carboot_event_id')) {
            return;
        }

        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropForeign(['carboot_event_id']);
            $table->dropIndex('feedbacks_carboot_event_id_index');
            $table->dropColumn('carboot_event_id');
        });
    }
};
