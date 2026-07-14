<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2A.5 — explicit operational event days (ADR-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->cascadeOnDelete();
            $table->date('operational_date');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('operational_status', 32)->default('active');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['carboot_event_id', 'operational_date'],
                'event_days_event_date_unique'
            );
            $table->index(
                ['carboot_event_id', 'operational_status'],
                'event_days_event_status_index'
            );
            $table->index(
                ['carboot_event_id', 'display_order'],
                'event_days_event_display_order_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_days');
    }
};
