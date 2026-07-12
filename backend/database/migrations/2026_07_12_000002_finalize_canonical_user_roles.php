<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.3C PR2 — shrink users.role to final canonical roles.
 *
 * Remaps:
 *   staff   -> cmart_management
 *   manager -> organizer (safety catch)
 *   uum     -> organizer (safety catch)
 *
 * Final ENUM: community | organizer | cmart_management | super_admin
 */
return new class extends Migration
{
    private const AUDIT_TABLE = 'role_cleanup_audit_202607_pr2';

    private const FINAL_ROLES = ['community', 'organizer', 'cmart_management', 'super_admin'];

    private const LEGACY_ROLES = ['community', 'staff', 'manager', 'organizer', 'cmart_management', 'super_admin', 'uum'];

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

        DB::statement(sprintf(
            'INSERT INTO %s (user_id, email, original_role, vendor_status, original_created_at, original_updated_at)
             SELECT id, email, role, vendor_status, created_at, updated_at FROM users',
            self::AUDIT_TABLE,
        ));

        DB::table('users')->where('role', 'staff')->update(['role' => 'cmart_management']);
        DB::table('users')->whereIn('role', ['manager', 'uum'])->update(['role' => 'organizer']);

        DB::statement("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'community'");

        DB::table('users')
            ->whereNotIn('role', self::FINAL_ROLES)
            ->update(['role' => 'community']);

        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('community', 'organizer', 'cmart_management', 'super_admin') NOT NULL DEFAULT 'community'"
        );
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'community'");

        if (Schema::hasTable(self::AUDIT_TABLE)) {
            DB::statement(sprintf(
                "UPDATE users u
                 JOIN %s a ON a.user_id = u.id
                 SET u.role = a.original_role
                 WHERE a.original_role IN ('staff', 'manager', 'uum')",
                self::AUDIT_TABLE,
            ));
        }

        DB::table('users')
            ->whereNotIn('role', self::LEGACY_ROLES)
            ->update(['role' => 'community']);

        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('community', 'staff', 'manager', 'organizer', 'cmart_management', 'super_admin', 'uum') NOT NULL DEFAULT 'community'"
        );

        Schema::dropIfExists(self::AUDIT_TABLE);
    }
};
