<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.8 — durable Organizer category mismatch override history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_category_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->unsignedBigInteger('booking_category_id_snapshot');
            $table->string('booking_category_label_snapshot', 255);
            $table->unsignedBigInteger('assigned_category_id_snapshot');
            $table->string('assigned_category_label_snapshot', 255);
            $table->json('assigned_row_ids_snapshot');
            $table->json('assigned_row_labels_snapshot');
            $table->json('assigned_site_ids_snapshot');
            $table->json('assigned_site_labels_snapshot');
            $table->text('reason');
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->string('status', 32);
            $table->unsignedTinyInteger('active_lock')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'active_lock'], 'booking_category_overrides_single_active');
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_category_overrides');
    }
};
