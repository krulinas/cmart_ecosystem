<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2A.6 — durable booking-day occupancy (ADR-012 / ADR-013).
 *
 * One row = one Booking occupying one EventSite on one EventDay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_day_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->restrictOnDelete();
            $table->foreignId('event_day_id')
                ->constrained('event_days')
                ->restrictOnDelete();
            $table->foreignId('event_site_id')
                ->constrained('event_sites')
                ->restrictOnDelete();
            $table->string('allocation_status', 20);
            $table->dateTime('reserved_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->foreignId('released_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('release_reason')->nullable();
            $table->unsignedTinyInteger('active_lock')->nullable();
            $table->timestamps();

            // Only one active occupant per site-day; NULL active_lock allows historical rows.
            $table->unique(
                ['event_day_id', 'event_site_id', 'active_lock'],
                'bda_day_site_active_lock_unique'
            );

            // One allocation row per booking/day/site (history reuses a new booking).
            $table->unique(
                ['booking_id', 'event_day_id', 'event_site_id'],
                'bda_booking_day_site_unique'
            );

            // Unique prefix covers booking_id alone; composite supports status filters.
            $table->index(
                ['booking_id', 'allocation_status'],
                'bda_booking_status_index'
            );
            // Unique (day, site, lock) covers day and day+site prefixes.
            $table->index('event_site_id', 'bda_event_site_id_index');
            $table->index('allocation_status', 'bda_allocation_status_index');
        });

        // MariaDB/MySQL: avoid three-valued NULL leak in CHECK (NULL result = accept).
        // COALESCE keeps occupying statuses false when active_lock is NULL.
        DB::statement("
            ALTER TABLE booking_day_allocations
            ADD CONSTRAINT bda_status_active_lock_check
            CHECK (
                (
                    allocation_status IN ('reserved', 'confirmed')
                    AND COALESCE(active_lock, 0) = 1
                )
                OR (
                    allocation_status IN ('released', 'cancelled')
                    AND active_lock IS NULL
                )
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_day_allocations');
    }
};
