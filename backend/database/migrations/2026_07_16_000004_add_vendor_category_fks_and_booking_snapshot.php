<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.4 — nullable category FKs and booking category label snapshot.
 *
 * Kept nullable so current Phase 2 string write paths remain compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('vendor_category_id')
                ->nullable()
                ->after('product_category')
                ->constrained('vendor_categories')
                ->restrictOnDelete();
            $table->string('category_label_snapshot', 128)
                ->nullable()
                ->after('vendor_category_id');
            $table->index('vendor_category_id', 'bookings_vendor_category_id_index');
        });

        Schema::table('vendor_business_profiles', function (Blueprint $table) {
            $table->foreignId('vendor_category_id')
                ->nullable()
                ->after('business_category')
                ->constrained('vendor_categories')
                ->restrictOnDelete();
            $table->index('vendor_category_id', 'vendor_business_profiles_vendor_category_id_index');
        });

        Schema::table('user_booking_preferences', function (Blueprint $table) {
            $table->foreignId('vendor_category_id')
                ->nullable()
                ->after('product_category')
                ->constrained('vendor_categories')
                ->restrictOnDelete();
            $table->index('vendor_category_id', 'user_booking_preferences_vendor_category_id_index');
        });

        Schema::table('vendor_items', function (Blueprint $table) {
            $table->foreignId('vendor_category_id')
                ->nullable()
                ->after('category')
                ->constrained('vendor_categories')
                ->restrictOnDelete();
            $table->index('vendor_category_id', 'vendor_items_vendor_category_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_category_id');
        });

        Schema::table('user_booking_preferences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_category_id');
        });

        Schema::table('vendor_business_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_category_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_category_id');
            $table->dropColumn('category_label_snapshot');
        });
    }
};
