<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'community'");

        DB::table('users')->where('role', 'cmart_staff')->update(['role' => 'staff']);
        DB::table('users')->where('role', 'cmart_admin')->update(['role' => 'manager']);

        DB::table('users')
            ->whereNotIn('role', ['community', 'staff', 'manager', 'super_admin', 'uum'])
            ->update(['role' => 'community']);

        DB::statement("ALTER TABLE users MODIFY role ENUM('community', 'staff', 'manager', 'super_admin', 'uum') NOT NULL DEFAULT 'community'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'community'");

        DB::table('users')->where('role', 'staff')->update(['role' => 'cmart_staff']);
        DB::table('users')->where('role', 'manager')->update(['role' => 'cmart_admin']);
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'cmart_admin']);

        DB::table('users')
            ->whereNotIn('role', ['community', 'cmart_staff', 'cmart_admin', 'uum'])
            ->update(['role' => 'community']);

        DB::statement("ALTER TABLE users MODIFY role ENUM('community', 'cmart_staff', 'cmart_admin', 'uum') NOT NULL DEFAULT 'community'");
    }
};
