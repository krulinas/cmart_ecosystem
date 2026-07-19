<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.3 — waiver actor/time evidence.
 *
 * The frozen Phase 4.2 schema stores charge_waive_reason but no waiver actor
 * or timestamp. Confirmation evidence (charge_confirmed_by/at) must remain
 * distinct from waiver evidence, so a focused additive pair is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_reservations', function (Blueprint $table) {
            $table->foreignId('charge_waived_by')
                ->nullable()
                ->after('charge_waive_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('charge_waived_at')
                ->nullable()
                ->after('charge_waived_by');
        });
    }

    public function down(): void
    {
        Schema::table('item_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('charge_waived_by');
            $table->dropColumn('charge_waived_at');
        });
    }
};
