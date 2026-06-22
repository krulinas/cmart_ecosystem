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
            'Cancelled',
            'Withdrawn'
        ) NOT NULL DEFAULT 'Pending_Staff'");

        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'withdrawn_at')) {
                $table->timestamp('withdrawn_at')->nullable()->after('vendor_request_note');
            }

            if (!Schema::hasColumn('bookings', 'withdrawal_reason')) {
                $table->text('withdrawal_reason')->nullable()->after('withdrawn_at');
            }

            if (!Schema::hasColumn('bookings', 'withdrawn_by')) {
                $table->foreignId('withdrawn_by')
                    ->nullable()
                    ->after('withdrawal_reason')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'withdrawn_by')) {
                $table->dropConstrainedForeignId('withdrawn_by');
            }

            if (Schema::hasColumn('bookings', 'withdrawal_reason')) {
                $table->dropColumn('withdrawal_reason');
            }

            if (Schema::hasColumn('bookings', 'withdrawn_at')) {
                $table->dropColumn('withdrawn_at');
            }
        });

        DB::statement("UPDATE bookings SET approval_status = 'Cancelled' WHERE approval_status = 'Withdrawn'");

        DB::statement("ALTER TABLE bookings MODIFY approval_status ENUM(
            'Pending_Staff',
            'Needs_Revision',
            'Pending_Boss',
            'Approved',
            'Rejected',
            'Cancelled'
        ) NOT NULL DEFAULT 'Pending_Staff'");
    }
};
