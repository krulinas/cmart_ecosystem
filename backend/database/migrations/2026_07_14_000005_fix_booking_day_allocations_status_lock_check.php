<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2A.6 follow-up — tighten status/active_lock CHECK against SQL NULL semantics.
 *
 * Original CHECK treated (reserved + active_lock NULL) as UNKNOWN, which MySQL/MariaDB
 * accept. COALESCE forces occupying statuses to fail closed when active_lock is NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Previous attempt may have dropped the constraint before ADD failed on bad rows.
        $this->dropCheckIfExists();

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
        $this->dropCheckIfExists();

        DB::statement("
            ALTER TABLE booking_day_allocations
            ADD CONSTRAINT bda_status_active_lock_check
            CHECK (
                (
                    allocation_status IN ('reserved', 'confirmed')
                    AND active_lock = 1
                )
                OR (
                    allocation_status IN ('released', 'cancelled')
                    AND active_lock IS NULL
                )
            )
        ");
    }

    private function dropCheckIfExists(): void
    {
        $exists = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'booking_day_allocations'
              AND CONSTRAINT_NAME = 'bda_status_active_lock_check'
              AND CONSTRAINT_TYPE = 'CHECK'
        ");

        if ($exists) {
            DB::statement('ALTER TABLE booking_day_allocations DROP CONSTRAINT bda_status_active_lock_check');
        }
    }
};
