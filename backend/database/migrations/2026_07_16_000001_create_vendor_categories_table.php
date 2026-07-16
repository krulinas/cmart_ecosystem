<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.4 — canonical vendor category taxonomy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64);
            $table->string('label', 128);
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique('slug', 'vendor_categories_slug_unique');
            $table->unique('label', 'vendor_categories_label_unique');
            $table->index('is_active', 'vendor_categories_is_active_index');
            $table->index('display_order', 'vendor_categories_display_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_categories');
    }
};
