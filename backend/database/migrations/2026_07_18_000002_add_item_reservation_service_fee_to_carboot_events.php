<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CHECK_NAME = 'carboot_events_item_reservation_fee_non_negative';

    public function up(): void
    {
        Schema::table('carboot_events', function (Blueprint $table) {
            $table->decimal('item_reservation_service_fee', 10, 2)
                ->nullable()
                ->after('max_slots');
        });

        DB::statement(sprintf(
            'ALTER TABLE carboot_events ADD CONSTRAINT %s CHECK (item_reservation_service_fee IS NULL OR item_reservation_service_fee >= 0)',
            self::CHECK_NAME,
        ));
    }

    public function down(): void
    {
        $this->dropCheckIfExists();

        Schema::table('carboot_events', function (Blueprint $table) {
            $table->dropColumn('item_reservation_service_fee');
        });
    }

    private function dropCheckIfExists(): void
    {
        $exists = DB::selectOne(
            <<<'SQL'
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'carboot_events'
                  AND CONSTRAINT_NAME = ?
                  AND CONSTRAINT_TYPE = 'CHECK'
            SQL,
            [self::CHECK_NAME],
        );

        if ($exists) {
            DB::statement('ALTER TABLE carboot_events DROP CONSTRAINT '.self::CHECK_NAME);
        }
    }
};
