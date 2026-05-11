<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expand bookings.approval_status to the 2-tier corporate approval pipeline:
     * Pending_Staff → Pending_Boss → Approved (with Needs_Revision / Rejected branches).
     */
    public function up()
    {
        // Temporarily allow legacy and new values together so existing rows survive the transition.
        DB::statement("ALTER TABLE bookings MODIFY approval_status ENUM(
            'Pending', 'Approved', 'Rejected',
            'Pending_Staff', 'Needs_Revision', 'Pending_Boss'
        ) NOT NULL DEFAULT 'Pending_Staff'");

        DB::table('bookings')->where('approval_status', 'Pending')->update([
            'approval_status' => 'Pending_Staff',
        ]);

        // Narrow to the final pipeline values.
        DB::statement("ALTER TABLE bookings MODIFY approval_status ENUM(
            'Pending_Staff', 'Needs_Revision', 'Pending_Boss', 'Approved', 'Rejected'
        ) NOT NULL DEFAULT 'Pending_Staff'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE bookings MODIFY approval_status ENUM(
            'Pending', 'Approved', 'Rejected',
            'Pending_Staff', 'Needs_Revision', 'Pending_Boss'
        ) NOT NULL DEFAULT 'Pending'");

        DB::table('bookings')
            ->whereIn('approval_status', ['Pending_Staff', 'Needs_Revision', 'Pending_Boss'])
            ->update(['approval_status' => 'Pending']);

        DB::statement("ALTER TABLE bookings MODIFY approval_status ENUM(
            'Pending', 'Approved', 'Rejected'
        ) NOT NULL DEFAULT 'Pending'");
    }
};
