<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY approval_status ENUM(
            'Pending_Staff',
            'Needs_Revision',
            'Pending_Boss',
            'Approved',
            'Rejected',
            'Cancelled'
        ) NOT NULL DEFAULT 'Pending_Staff'");

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('vendor_request_type')->nullable()->after('revision_comment');
            $table->text('vendor_request_note')->nullable()->after('vendor_request_type');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['vendor_request_type', 'vendor_request_note']);
        });

        DB::statement("ALTER TABLE bookings MODIFY approval_status ENUM(
            'Pending_Staff',
            'Needs_Revision',
            'Pending_Boss',
            'Approved',
            'Rejected'
        ) NOT NULL DEFAULT 'Pending_Staff'");
    }
};
