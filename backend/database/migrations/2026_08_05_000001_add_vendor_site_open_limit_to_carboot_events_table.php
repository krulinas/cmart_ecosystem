<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 Layout Guardrails — dedicated vendor booth open capacity.
 *
 * Distinct from max_slots (community RSVP). Do not conflate the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carboot_events', function (Blueprint $table) {
            $table->unsignedInteger('vendor_site_open_limit')
                ->nullable()
                ->after('max_slots');
        });
    }

    public function down(): void
    {
        Schema::table('carboot_events', function (Blueprint $table) {
            $table->dropColumn('vendor_site_open_limit');
        });
    }
};
