<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'vendor_status')) {
                $table->enum('vendor_status', ['none', 'pending', 'approved', 'suspended'])
                    ->default('none')
                    ->after('role');
            }
        });

        // Temporarily allow old and new role values so existing rows can be normalized safely.
        DB::statement("ALTER TABLE users MODIFY role ENUM('Vendor', 'Admin', 'Community', 'community', 'cmart_staff', 'cmart_admin', 'uum') DEFAULT 'community'");

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

        DB::statement("ALTER TABLE users MODIFY role ENUM('community', 'cmart_staff', 'cmart_admin', 'uum') DEFAULT 'community'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('Vendor', 'Admin', 'Community', 'community', 'cmart_staff', 'cmart_admin', 'uum') DEFAULT 'Community'");

        DB::table('users')->where('role', 'community')->where('vendor_status', 'approved')->update(['role' => 'Vendor']);
        DB::table('users')->where('role', 'community')->update(['role' => 'Community']);
        DB::table('users')->whereIn('role', ['cmart_staff', 'cmart_admin'])->update(['role' => 'Admin']);
        DB::table('users')->where('role', 'uum')->update(['role' => 'Community']);

        DB::statement("ALTER TABLE users MODIFY role ENUM('Vendor', 'Admin', 'Community') DEFAULT 'Community'");

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'vendor_status')) {
                $table->dropColumn('vendor_status');
            }
        });
    }
};
