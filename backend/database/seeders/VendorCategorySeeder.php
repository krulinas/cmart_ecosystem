<?php

namespace Database\Seeders;

use App\Support\Migrations\Phase34SchemaBackfill;
use Illuminate\Database\Seeder;

/**
 * Idempotent canonical vendor category seed (Phase 3.4).
 *
 * Prefer migration 2026_07_16_000002 for schema correctness; this seeder
 * remains safe to re-run for local/demo environments.
 */
class VendorCategorySeeder extends Seeder
{
    public function run(): void
    {
        Phase34SchemaBackfill::seedCanonicalCategories();
    }
}
