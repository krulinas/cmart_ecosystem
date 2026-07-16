<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.4A — event deletion ordering for restrictive site→row FK.
 *
 * InnoDB sibling CASCADE from carboot_events to both event_sites and
 * event_layout_rows can attempt to delete rows while sites still reference
 * them, which fails under RESTRICT. This BEFORE DELETE trigger deletes sites
 * first (allocation RESTRICT still blocks when history exists), then empty
 * rows, so normal event deletion remains possible without nullOnDelete.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cmart_before_delete_carboot_event_layout');

        DB::unprepared("
            CREATE TRIGGER cmart_before_delete_carboot_event_layout
            BEFORE DELETE ON carboot_events
            FOR EACH ROW
            BEGIN
                DELETE FROM event_sites WHERE carboot_event_id = OLD.id;
                DELETE FROM event_layout_rows WHERE carboot_event_id = OLD.id;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cmart_before_delete_carboot_event_layout');
    }
};
