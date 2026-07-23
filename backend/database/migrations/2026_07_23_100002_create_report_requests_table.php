<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMart → Organizer report request queue.
 *
 * Active uniqueness (one open request per event + type) is enforced in services;
 * the composite index supports that lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->restrictOnDelete();
            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('report_type', 64);
            $table->text('message')->nullable();
            $table->date('preferred_due_date')->nullable();
            $table->string('status', 32)->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('declined_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(
                ['carboot_event_id', 'report_type', 'status'],
                'report_requests_event_type_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_requests');
    }
};
