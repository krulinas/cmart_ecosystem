<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event-level parking site price + organizer default price.
 *
 * New bookings use carboot_events.site_price × selected site count.
 * Historical invoice amounts are not modified.
 */
return new class extends Migration
{
    private const EVENT_CHECK = 'carboot_events_site_price_positive';

    private const USER_CHECK = 'users_default_site_price_positive';

    public function up(): void
    {
        Schema::table('carboot_events', function (Blueprint $table) {
            $table->decimal('site_price', 10, 2)
                ->default('20.00')
                ->after('item_reservation_service_fee');
        });

        // Ensure every existing event has the product default (idempotent).
        DB::table('carboot_events')
            ->whereNull('site_price')
            ->orWhere('site_price', '<=', 0)
            ->update(['site_price' => '20.00']);

        DB::statement(sprintf(
            'ALTER TABLE carboot_events ADD CONSTRAINT %s CHECK (site_price > 0)',
            self::EVENT_CHECK,
        ));

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('default_site_price', 10, 2)
                ->nullable()
                ->after('vendor_status');
        });

        DB::statement(sprintf(
            'ALTER TABLE users ADD CONSTRAINT %s CHECK (default_site_price IS NULL OR default_site_price > 0)',
            self::USER_CHECK,
        ));

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('unit_site_price', 10, 2)
                ->nullable()
                ->after('product_details');
            $table->unsignedInteger('site_quantity')
                ->nullable()
                ->after('unit_site_price');
        });
    }

    public function down(): void
    {
        $this->dropCheckIfExists('carboot_events', self::EVENT_CHECK);
        $this->dropCheckIfExists('users', self::USER_CHECK);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['unit_site_price', 'site_quantity']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_site_price');
        });

        Schema::table('carboot_events', function (Blueprint $table) {
            $table->dropColumn('site_price');
        });
    }

    private function dropCheckIfExists(string $table, string $constraint): void
    {
        $exists = DB::selectOne(
            <<<'SQL'
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
                  AND CONSTRAINT_TYPE = 'CHECK'
            SQL,
            [$table, $constraint],
        );

        if ($exists) {
            DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $table, $constraint));
        }
    }
};
