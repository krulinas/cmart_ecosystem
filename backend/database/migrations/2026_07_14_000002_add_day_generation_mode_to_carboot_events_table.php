<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2A.5 — Organizer-controlled day generation mode (ADR-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carboot_events', function (Blueprint $table) {
            if (! Schema::hasColumn('carboot_events', 'day_generation_mode')) {
                $table->string('day_generation_mode', 32)
                    ->default('calendar_days')
                    ->after('max_slots');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carboot_events', function (Blueprint $table) {
            if (Schema::hasColumn('carboot_events', 'day_generation_mode')) {
                $table->dropColumn('day_generation_mode');
            }
        });
    }
};
