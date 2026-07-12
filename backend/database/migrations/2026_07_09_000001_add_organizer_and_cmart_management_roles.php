<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'community'");

        DB::table('users')
            ->whereNotIn('role', ['community', 'staff', 'manager', 'organizer', 'cmart_management', 'super_admin', 'uum'])
            ->update(['role' => 'community']);

        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('community', 'staff', 'manager', 'organizer', 'cmart_management', 'super_admin', 'uum') NOT NULL DEFAULT 'community'"
        );
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'community'");

        DB::table('users')->where('role', 'organizer')->update(['role' => 'manager']);
        DB::table('users')->where('role', 'cmart_management')->update(['role' => 'staff']);

        DB::table('users')
            ->whereNotIn('role', ['community', 'staff', 'manager', 'super_admin', 'uum'])
            ->update(['role' => 'community']);

        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('community', 'staff', 'manager', 'super_admin', 'uum') NOT NULL DEFAULT 'community'"
        );
    }
};
