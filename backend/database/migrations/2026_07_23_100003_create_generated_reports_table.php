<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned event report snapshots (draft → published → superseded).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->restrictOnDelete();
            $table->foreignId('report_request_id')
                ->nullable()
                ->constrained('report_requests')
                ->nullOnDelete();
            $table->string('report_type', 64);
            $table->unsignedInteger('version');
            $table->string('status', 32);
            $table->json('snapshot');
            $table->text('organizer_observations')->nullable();
            $table->text('organizer_recommendations')->nullable();
            $table->foreignId('prepared_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->string('revision_reason')->nullable();
            $table->foreignId('supersedes_report_id')
                ->nullable()
                ->constrained('generated_reports')
                ->nullOnDelete();
            $table->string('event_title_snapshot');
            $table->dateTime('event_starts_at_snapshot')->nullable();
            $table->dateTime('event_ends_at_snapshot')->nullable();
            $table->timestamps();

            $table->unique(
                ['carboot_event_id', 'report_type', 'version'],
                'generated_reports_event_type_version_unique'
            );
            $table->index(['carboot_event_id', 'status'], 'generated_reports_event_status_index');
            $table->index('report_type', 'generated_reports_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
