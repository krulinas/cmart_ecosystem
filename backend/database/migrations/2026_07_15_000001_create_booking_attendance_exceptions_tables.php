<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_attendance_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->string('applied_by_name');
            $table->text('reason');
            $table->string('payment_state', 30);
            $table->boolean('no_refund_acknowledged')->default(false);
            $table->unsignedSmallInteger('previous_retained_day_count');
            $table->unsignedSmallInteger('retained_day_count');
            $table->unsignedSmallInteger('released_day_count');
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['booking_id', 'applied_at'], 'bae_booking_applied_index');
        });

        Schema::create('booking_attendance_exception_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_attendance_exception_id');
            $table->foreign(
                'booking_attendance_exception_id',
                'baed_exception_foreign',
            )
                ->references('id')
                ->on('booking_attendance_exceptions')
                ->cascadeOnDelete();
            $table->foreignId('event_day_id')->constrained()->restrictOnDelete();
            $table->string('disposition', 20);
            $table->timestamps();

            $table->unique(
                ['booking_attendance_exception_id', 'event_day_id'],
                'baed_exception_day_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_attendance_exception_days');
        Schema::dropIfExists('booking_attendance_exceptions');
    }
};
