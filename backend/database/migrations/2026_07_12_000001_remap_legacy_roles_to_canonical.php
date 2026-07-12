<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.3C PR1 — canonical role identity data migration (Option B, compatibility-safe).
 *
 * Remaps legacy Organizer identities onto the canonical `organizer` role:
 *   manager -> organizer
 *   uum     -> organizer
 *
 * Deliberately NOT done here (PR2 coordinated cutover):
 *   - staff -> cmart_management (staff must keep the Pending_Staff booking
 *     stage working until PR2 replaces the two-stage pipeline with direct
 *     Organizer review)
 *   - users.role ENUM shrink (legacy values stay writable so the current
 *     booking workflow and its tests keep passing until PR2)
 *
 * A full snapshot of users (id, email, role, vendor_status, timestamps) is
 * written to `role_migration_audit_202607` before any remap so the change is
 * auditable and reversible.
 */
return new class extends Migration
{
    private const AUDIT_TABLE = 'role_migration_audit_202607';

    public function up(): void
    {
        if (!Schema::hasTable(self::AUDIT_TABLE)) {
            Schema::create(self::AUDIT_TABLE, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('email');
                $table->string('original_role', 32);
                $table->string('vendor_status', 32)->nullable();
                $table->timestamp('original_created_at')->nullable();
                $table->timestamp('original_updated_at')->nullable();
                $table->timestamp('snapshot_at')->useCurrent();
            });
        }

        // Snapshot every user before remapping (small table; full snapshot
        // keeps rollback simple and preserves who was manager vs uum).
        DB::statement(sprintf(
            'INSERT INTO %s (user_id, email, original_role, vendor_status, original_created_at, original_updated_at)
             SELECT id, email, role, vendor_status, created_at, updated_at FROM users',
            self::AUDIT_TABLE,
        ));

        DB::table('users')
            ->whereIn('role', ['manager', 'uum'])
            ->update(['role' => 'organizer']);
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::AUDIT_TABLE)) {
            // Nothing to restore from; users created after the snapshot keep
            // their current roles. Restoration is impossible without the audit table.
            return;
        }

        // Restore only rows this migration changed. Users created after the
        // snapshot are untouched (they never had a legacy role).
        DB::statement(sprintf(
            "UPDATE users u
             JOIN %s a ON a.user_id = u.id
             SET u.role = a.original_role
             WHERE a.original_role IN ('manager', 'uum')",
            self::AUDIT_TABLE,
        ));

        Schema::dropIfExists(self::AUDIT_TABLE);
    }
};
