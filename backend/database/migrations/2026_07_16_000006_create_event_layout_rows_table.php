<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.4 — first-class event layout rows (one category per row).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_layout_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carboot_event_id')
                ->constrained('carboot_events')
                ->cascadeOnDelete();
            $table->foreignId('vendor_category_id')
                ->nullable()
                ->constrained('vendor_categories')
                ->restrictOnDelete();
            $table->string('label', 32);
            $table->string('slug', 64);
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['carboot_event_id', 'label'], 'event_layout_rows_event_label_unique');
            $table->unique(['carboot_event_id', 'slug'], 'event_layout_rows_event_slug_unique');
            $table->index(['carboot_event_id', 'display_order'], 'event_layout_rows_event_display_order_index');
            $table->index(['carboot_event_id', 'is_active'], 'event_layout_rows_event_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_layout_rows');
    }
};
