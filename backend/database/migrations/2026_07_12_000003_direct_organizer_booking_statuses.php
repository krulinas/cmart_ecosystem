<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.3C PR2 — direct Organizer booking workflow statuses.
 *
 * Remaps:
 *   Pending_Staff -> Pending_Organizer
 *   Pending_Boss  -> Pending_Organizer
 *
 * Final ENUM: Pending_Organizer | Needs_Revision | Approved | Rejected | Cancelled | Withdrawn
 */
return new class extends Migration
{
    private const AUDIT_TABLE = 'booking_status_migration_audit_202607';

    private const FINAL_STATUSES = [
        'Pending_Organizer',
        'Needs_Revision',
        'Approved',
        'Rejected',
        'Cancelled',
        'Withdrawn',
    ];

    private const LEGACY_STATUSES = [
        'Pending_Staff',
        'Needs_Revision',
        'Pending_Boss',
        'Approved',
        'Rejected',
        'Cancelled',
        'Withdrawn',
    ];

    public function up(): void
    {
        if (!Schema::hasTable(self::AUDIT_TABLE)) {
            Schema::create(self::AUDIT_TABLE, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('original_approval_status', 32);
                $table->timestamp('original_created_at')->nullable();
                $table->timestamp('original_updated_at')->nullable();
                $table->timestamp('snapshot_at')->useCurrent();
            });
        }

        DB::statement(sprintf(
            'INSERT INTO %s (booking_id, user_id, original_approval_status, original_created_at, original_updated_at)
             SELECT id, user_id, approval_status, created_at, updated_at FROM bookings',
            self::AUDIT_TABLE,
        ));

        DB::table('bookings')
            ->whereIn('approval_status', ['Pending_Staff', 'Pending_Boss'])
            ->update(['approval_status' => 'Pending_Organizer']);

        DB::statement("ALTER TABLE bookings MODIFY approval_status VARCHAR(32) NOT NULL DEFAULT 'Pending_Organizer'");

        DB::table('bookings')
            ->whereNotIn('approval_status', self::FINAL_STATUSES)
            ->update(['approval_status' => 'Pending_Organizer']);

        DB::statement(
            "ALTER TABLE bookings MODIFY approval_status ENUM('Pending_Organizer', 'Needs_Revision', 'Approved', 'Rejected', 'Cancelled', 'Withdrawn') NOT NULL DEFAULT 'Pending_Organizer'"
        );
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY approval_status VARCHAR(32) NOT NULL DEFAULT 'Pending_Staff'");

        if (Schema::hasTable(self::AUDIT_TABLE)) {
            DB::statement(sprintf(
                "UPDATE bookings b
                 JOIN %s a ON a.booking_id = b.id
                 SET b.approval_status = a.original_approval_status
                 WHERE a.original_approval_status IN ('Pending_Staff', 'Pending_Boss')",
                self::AUDIT_TABLE,
            ));
        }

        DB::table('bookings')
            ->whereNotIn('approval_status', self::LEGACY_STATUSES)
            ->update(['approval_status' => 'Pending_Staff']);

        DB::statement(
            "ALTER TABLE bookings MODIFY approval_status ENUM('Pending_Staff', 'Needs_Revision', 'Pending_Boss', 'Approved', 'Rejected', 'Cancelled', 'Withdrawn') NOT NULL DEFAULT 'Pending_Staff'"
        );

        Schema::dropIfExists(self::AUDIT_TABLE);
    }
};
