<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL ENUM is case-insensitive when detecting duplicate values, so an
     * intermediate enum containing both 'Community' and 'community' fails with
     * SQLSTATE 1291. To work around this we temporarily widen the column to
     * VARCHAR, normalize the data, then narrow back to the final ENUM.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'vendor_status')) {
                $table->enum('vendor_status', ['none', 'pending', 'approved', 'suspended'])
                    ->default('none')
                    ->after('role');
            }
        });

        // Step 1: widen to VARCHAR so case-conflicting values can coexist.
        DB::statement("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'community'");

        // Step 2: normalize legacy values to the new RBAC vocabulary.
        DB::table('users')->where('role', 'Vendor')->update([
            'role' => 'community',
            'vendor_status' => 'approved',
        ]);
        DB::table('users')->where('role', 'Admin')->update([
            'role' => 'cmart_admin',
            'vendor_status' => 'none',
        ]);
        DB::table('users')->where('role', 'Community')->update([
            'role' => 'community',
            'vendor_status' => 'none',
        ]);

        // Step 3: catch-all safety net so the next ALTER cannot fail on stray data.
        DB::table('users')
            ->whereNotIn('role', ['community', 'cmart_staff', 'cmart_admin', 'uum'])
            ->update(['role' => 'community']);

        // Step 4: narrow to the final ENUM.
        DB::statement("ALTER TABLE users MODIFY role ENUM('community', 'cmart_staff', 'cmart_admin', 'uum') NOT NULL DEFAULT 'community'");
    }

    public function down()
    {
        // Symmetric reverse: VARCHAR -> normalize -> legacy ENUM.
        DB::statement("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'Community'");

        DB::table('users')
            ->where('role', 'community')
            ->where('vendor_status', 'approved')
            ->update(['role' => 'Vendor']);
        DB::table('users')->where('role', 'community')->update(['role' => 'Community']);
        DB::table('users')->whereIn('role', ['cmart_staff', 'cmart_admin'])->update(['role' => 'Admin']);
        DB::table('users')->where('role', 'uum')->update(['role' => 'Community']);

        DB::table('users')
            ->whereNotIn('role', ['Vendor', 'Admin', 'Community'])
            ->update(['role' => 'Community']);

        DB::statement("ALTER TABLE users MODIFY role ENUM('Vendor', 'Admin', 'Community') NOT NULL DEFAULT 'Community'");

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'vendor_status')) {
                $table->dropColumn('vendor_status');
            }
        });
    }
};
