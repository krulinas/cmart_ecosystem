<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2A.4 — physical event-scoped parking bays (ADR-002).
 *
 * Distinct from `spaces` (booth/site-type catalogue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->cascadeOnDelete();
            $table->foreignId('space_id')
                ->constrained('spaces')
                ->restrictOnDelete();
            $table->string('label', 32);
            $table->string('row_label', 16);
            $table->unsignedInteger('position_number');
            $table->unsignedInteger('grid_row');
            $table->unsignedInteger('grid_column');
            $table->unsignedInteger('display_order')->default(0);
            $table->string('operational_status', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['carboot_event_id', 'label'], 'event_sites_event_label_unique');
            $table->unique(
                ['carboot_event_id', 'row_label', 'position_number'],
                'event_sites_event_row_position_unique'
            );
            $table->index(
                ['carboot_event_id', 'operational_status'],
                'event_sites_event_status_index'
            );
            $table->index(
                ['carboot_event_id', 'display_order'],
                'event_sites_event_display_order_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sites');
    }
};
