<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cached event-scoped analytics bundles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->restrictOnDelete();
            $table->string('metric_key', 64);
            $table->string('calculation_version', 32);
            $table->json('payload')->nullable();
            $table->string('source_fingerprint', 128)->nullable();
            $table->foreignId('import_batch_id')
                ->nullable()
                ->constrained('raw_survey_uploads')
                ->nullOnDelete();
            $table->string('status', 32)->default('ready');
            $table->timestamp('computed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['carboot_event_id', 'metric_key', 'calculation_version'],
                'analytics_results_event_metric_version_unique'
            );
            $table->index(['carboot_event_id', 'status'], 'analytics_results_event_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_results');
    }
};
