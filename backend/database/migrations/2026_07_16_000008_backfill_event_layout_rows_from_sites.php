<?php

use App\Support\Migrations\Phase34SchemaBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 3.4 — backfill event_layout_rows from distinct event + row_label.
 *
 * Does not invent categories for rows. Does not alter row_label values on sites
 * beyond linking via event_layout_row_id (labels already normalized at grouping).
 */
return new class extends Migration
{
    public function up(): void
    {
        Phase34SchemaBackfill::backfillEventLayoutRows();
    }

    public function down(): void
    {
        // Site FKs and rows are removed by earlier migration downs in reverse order.
    }
};
