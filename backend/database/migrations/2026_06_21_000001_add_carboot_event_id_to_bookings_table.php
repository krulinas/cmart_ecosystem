<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy rows with zero dates break strict-mode ALTER TABLE operations.
        DB::statement("UPDATE bookings SET booking_date = '1970-01-01' WHERE booking_date IS NULL OR booking_date < '1971-01-01'");

        if (Schema::hasColumn('bookings', 'carboot_event_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('carboot_event_id')
                ->nullable()
                ->after('space_id')
                ->constrained('carboot_events')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('bookings', 'carboot_event_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carboot_event_id');
        });
    }
};
