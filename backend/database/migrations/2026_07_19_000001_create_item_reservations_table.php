<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('public_reference', 20);
            $table->foreignId('vendor_item_id')->constrained('vendor_items')->restrictOnDelete();
            $table->foreignId('reserving_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('carboot_event_id')->constrained('carboot_events')->restrictOnDelete();
            $table->foreignId('vendor_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('reservation_status', 32);
            $table->unsignedTinyInteger('active_lock')->nullable();
            $table->decimal('service_fee_amount', 10, 2);
            $table->char('service_fee_currency', 3)->default('MYR');
            $table->string('charge_status', 32);
            $table->text('charge_confirmation_note')->nullable();
            $table->foreignId('charge_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('charge_confirmed_at')->nullable();
            $table->text('charge_waive_reason')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('expired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expired_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->string('item_name_snapshot');
            $table->timestamps();

            $table->unique('public_reference', 'item_reservations_public_reference_unique');
            $table->unique(
                ['vendor_item_id', 'active_lock'],
                'item_reservations_item_active_lock_unique',
            );
            $table->index(
                ['reserving_user_id', 'reservation_status'],
                'item_reservations_reserver_status_index',
            );
            $table->index(
                ['vendor_user_id', 'reservation_status'],
                'item_reservations_vendor_status_index',
            );
            $table->index(
                ['carboot_event_id', 'reservation_status'],
                'item_reservations_event_status_index',
            );
            $table->index(
                ['vendor_item_id', 'reservation_status'],
                'item_reservations_item_status_index',
            );
            $table->index('charge_status', 'item_reservations_charge_status_index');
        });

        DB::statement("
            ALTER TABLE item_reservations
            ADD CONSTRAINT item_reservations_status_active_lock_check
            CHECK (
                (
                    reservation_status IN ('pending_charge', 'confirmed')
                    AND COALESCE(active_lock, 0) = 1
                )
                OR (
                    reservation_status IN ('cancelled', 'expired', 'completed')
                    AND active_lock IS NULL
                )
            )
        ");

        DB::statement('
            ALTER TABLE item_reservations
            ADD CONSTRAINT item_reservations_service_fee_non_negative
            CHECK (service_fee_amount >= 0)
        ');

        DB::statement("
            ALTER TABLE item_reservations
            ADD CONSTRAINT item_reservations_charge_status_check
            CHECK (charge_status IN ('required', 'confirmed', 'waived', 'not_required', 'cancelled'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('item_reservations');
    }
};
