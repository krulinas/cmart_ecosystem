<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for report requests and generated reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_workflow_audits', function (Blueprint $table) {
            $table->id();
            $table->string('action', 64);
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('report_request_id')
                ->nullable()
                ->constrained('report_requests')
                ->nullOnDelete();
            $table->foreignId('generated_report_id')
                ->nullable()
                ->constrained('generated_reports')
                ->nullOnDelete();
            $table->foreignId('carboot_event_id')
                ->nullable()
                ->constrained('carboot_events')
                ->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['report_request_id', 'created_at'], 'rwa_request_created_index');
            $table->index(['generated_report_id', 'created_at'], 'rwa_report_created_index');
            $table->index(['carboot_event_id', 'action'], 'rwa_event_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_workflow_audits');
    }
};
