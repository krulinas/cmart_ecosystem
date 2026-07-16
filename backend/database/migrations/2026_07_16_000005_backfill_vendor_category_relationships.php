<?php

use App\Support\Migrations\Phase34SchemaBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 3.4 — backfill category FKs + booking snapshots; audit unknowns.
 *
 * Does not rewrite legacy string columns. Does not set NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Phase34SchemaBackfill::backfillCategoryRelationships();
    }

    public function down(): void
    {
        // Audit rows and FK values are intentionally left in place when rolling
        // back only this step; full rollback of Phase 3.4 drops FKs/audit table.
        // Clearing FKs here would destroy resolved mapping without restoring unknowns.
    }
};
