<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot table linking community users to carboot events they have registered for.
 *
 * A unique index on (carboot_event_id, user_id) prevents duplicate registrations
 * even if two HTTP requests somehow bypass application-level checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_user', function (Blueprint $table) {
            $table->id();

            // The event the user wants to join.
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->cascadeOnDelete();

            // The logged-in user who registered.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // When the registration was confirmed (separate from created_at for clarity).
            $table->timestamp('registered_at')->useCurrent();

            $table->timestamps();

            // One row per user per event — critical for concurrency safety at the DB layer.
            $table->unique(['carboot_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_user');
    }
};
