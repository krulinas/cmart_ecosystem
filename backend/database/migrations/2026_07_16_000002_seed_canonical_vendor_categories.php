<?php

use App\Support\Migrations\Phase34SchemaBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.4 — idempotent insert of seven canonical vendor categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Phase34SchemaBackfill::seedCanonicalCategories();
    }

    public function down(): void
    {
        $slugs = [
            'pre-loved-thrift',
            'food-beverages',
            'clothing-apparel',
            'handicrafts-art',
            'electronics-gadgets',
            'household-items',
            'mixed-others',
        ];

        DB::table('vendor_categories')->whereIn('slug', $slugs)->delete();
    }
};
