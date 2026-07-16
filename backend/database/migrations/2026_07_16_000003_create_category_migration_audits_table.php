<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.4 — append-only audit for legacy category mapping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_migration_audits', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 64);
            $table->unsignedBigInteger('source_primary_key');
            $table->string('source_column', 64);
            $table->string('original_value', 255)->nullable();
            $table->string('normalized_value', 255)->nullable();
            $table->string('mapping_status', 32);
            $table->foreignId('matched_vendor_category_id')
                ->nullable()
                ->constrained('vendor_categories')
                ->nullOnDelete();
            $table->string('reason_code', 64);
            $table->string('backfill_version', 32);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_table', 'source_primary_key', 'source_column', 'backfill_version'],
                'category_migration_audits_source_unique',
            );
            $table->index('mapping_status', 'category_migration_audits_status_index');
            $table->index('reason_code', 'category_migration_audits_reason_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_migration_audits');
    }
};
