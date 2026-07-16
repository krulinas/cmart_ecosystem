<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.5 — append-only Organizer layout mutation audit.
 *
 * booking_audit_logs requires booking_id and cannot store layout actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_layout_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->cascadeOnDelete();
            $table->foreignId('actor_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('action', 64);
            $table->unsignedBigInteger('event_layout_row_id')->nullable();
            $table->unsignedBigInteger('event_site_id')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['carboot_event_id', 'action'], 'elal_event_action_index');
            $table->index('event_layout_row_id', 'elal_row_id_index');
            $table->index('event_site_id', 'elal_site_id_index');
            $table->index('created_at', 'elal_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_layout_audit_logs');
    }
};
