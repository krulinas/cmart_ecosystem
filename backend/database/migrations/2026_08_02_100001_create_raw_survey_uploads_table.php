<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preserves original vendor survey CSV uploads per carboot event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_survey_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->restrictOnDelete();
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('schema_name', 64);
            $table->string('schema_version', 32);
            $table->string('original_filename');
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('sha256', 64);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('total_row_count')->default(0);
            $table->unsignedInteger('valid_row_count')->default(0);
            $table->unsignedInteger('invalid_row_count')->default(0);
            $table->json('validation_summary')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_finished_at')->nullable();
            $table->timestamps();

            $table->index(['carboot_event_id', 'status'], 'raw_survey_uploads_event_status_index');
            $table->index(['carboot_event_id', 'sha256'], 'raw_survey_uploads_event_checksum_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_survey_uploads');
    }
};
