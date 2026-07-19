<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_reservation_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_reservation_id')
                ->constrained('item_reservations')
                ->restrictOnDelete();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 64);
            $table->string('from_reservation_status', 32)->nullable();
            $table->string('to_reservation_status', 32)->nullable();
            $table->string('from_charge_status', 32)->nullable();
            $table->string('to_charge_status', 32)->nullable();
            $table->string('note', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['item_reservation_id', 'created_at'],
                'item_reservation_audits_reservation_created_index',
            );
            $table->index('action', 'item_reservation_audits_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_reservation_audits');
    }
};
