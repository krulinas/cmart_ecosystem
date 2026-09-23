<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_item_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_item_id');
            $table->unsignedBigInteger('vendor_user_id');
            $table->unsignedBigInteger('carboot_event_id');
            $table->unsignedBigInteger('vendor_booking_id')->nullable();
            $table->unsignedBigInteger('item_reservation_id')->nullable();
            $table->string('sale_source', 32);
            $table->string('item_name_snapshot');
            $table->decimal('asking_price_snapshot', 10, 2)->nullable();
            $table->decimal('final_sale_price', 10, 2);
            $table->char('currency', 3)->default('MYR');
            $table->dateTime('sold_at');
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->timestamps();

            $table->unique('vendor_item_id', 'vendor_item_sales_item_unique');
            $table->unique('item_reservation_id', 'vendor_item_sales_reservation_unique');
            $table->index(
                ['vendor_user_id', 'sold_at'],
                'vendor_item_sales_vendor_sold_index',
            );
            $table->index(
                ['carboot_event_id', 'sale_source'],
                'vendor_item_sales_event_source_index',
            );

            $table->foreign('vendor_item_id', 'vendor_item_sales_vendor_item_id_foreign')
                ->references('id')->on('vendor_items')->restrictOnDelete();
            $table->foreign('vendor_user_id', 'vendor_item_sales_vendor_user_id_foreign')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('carboot_event_id', 'vendor_item_sales_carboot_event_id_foreign')
                ->references('id')->on('carboot_events')->restrictOnDelete();
            $table->foreign('vendor_booking_id', 'vendor_item_sales_vendor_booking_id_foreign')
                ->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('item_reservation_id', 'vendor_item_sales_item_reservation_id_foreign')
                ->references('id')->on('item_reservations')->nullOnDelete();
            $table->foreign('recorded_by_user_id', 'vendor_item_sales_recorded_by_user_id_foreign')
                ->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("
            ALTER TABLE vendor_item_sales
            ADD CONSTRAINT vendor_item_sales_source_check
            CHECK (sale_source IN ('reserved', 'walk_in'))
        ");

        DB::statement('
            ALTER TABLE vendor_item_sales
            ADD CONSTRAINT vendor_item_sales_final_price_non_negative
            CHECK (final_sale_price >= 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_item_sales');
    }
};
