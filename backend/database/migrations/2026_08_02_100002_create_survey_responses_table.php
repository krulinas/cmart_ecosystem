<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normalized vendor post-event survey rows (vendor_post_event_v1).
 * Separate from community feedbacks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->restrictOnDelete();
            $table->foreignId('import_batch_id')
                ->constrained('raw_survey_uploads')
                ->cascadeOnDelete();
            $table->string('respondent_id', 64);
            $table->unsignedInteger('source_row_number');
            $table->string('schema_name', 64);
            $table->string('schema_version', 32);

            $table->json('product_categories')->nullable();
            $table->text('product_categories_other_text')->nullable();
            $table->json('item_conditions')->nullable();
            $table->boolean('has_difficulty')->nullable();
            $table->text('difficulty_details')->nullable();
            $table->json('event_info_sources')->nullable();
            $table->text('event_info_sources_other_text')->nullable();
            $table->string('items_sold_band', 128)->nullable();
            $table->string('gross_sales_band', 128)->nullable();
            $table->json('unsold_item_actions')->nullable();
            $table->string('sales_purpose', 128)->nullable();
            $table->string('experience_rating', 128)->nullable();
            $table->json('improvement_areas')->nullable();
            $table->text('improvement_areas_other_text')->nullable();
            $table->text('comments_and_suggestions')->nullable();
            $table->string('supporting_activity_attracted_visitors', 64)->nullable();
            $table->json('supporting_activity_impacts')->nullable();
            $table->text('supporting_activity_impacts_other_text')->nullable();
            $table->text('import_auto_review_flags')->nullable();
            $table->text('import_review_notes')->nullable();
            $table->string('validation_status', 32)->default('valid');
            $table->timestamps();

            $table->unique(
                ['import_batch_id', 'respondent_id'],
                'survey_responses_batch_respondent_unique'
            );
            $table->index(['carboot_event_id', 'import_batch_id'], 'survey_responses_event_batch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
