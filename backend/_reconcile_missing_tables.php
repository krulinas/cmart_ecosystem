<?php

/**
 * Local recovery helper: recreate a single missing/broken table while preserving
 * its existing migrations-table record. Not a permanent application migration.
 *
 * Usage:
 *   php artisan tinker-equivalent via: php _reconcile_missing_tables.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$db = DB::connection()->getDatabaseName();
if ($db !== 'cmart_db_rebuild') {
    fwrite(STDERR, "Refusing to run: active database is [{$db}], expected cmart_db_rebuild.\n");
    exit(1);
}

echo "active_db={$db}\n";

function tableUsable(string $table): bool
{
    if (! Schema::hasTable($table)) {
        return false;
    }

    try {
        DB::table($table)->limit(1)->count();

        return true;
    } catch (Throwable) {
        return false;
    }
}

// --- booking_audit_logs ---
$auditMigration = '2026_06_03_000001_create_booking_audit_logs_table';
$auditRecorded = DB::table('migrations')->where('migration', $auditMigration)->exists();
$auditUsable = tableUsable('booking_audit_logs');

echo "booking_audit_logs recorded=".($auditRecorded ? '1' : '0')." usable=".($auditUsable ? '1' : '0')."\n";

if ($auditRecorded && ! $auditUsable) {
    // Discard broken metadata/tablespace, then recreate empty schema.
    try {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('booking_audit_logs');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        echo "booking_audit_logs: dropped broken table definition\n";
    } catch (Throwable $e) {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        echo 'booking_audit_logs drop warning: '.$e->getMessage()."\n";
    }

    Schema::create('booking_audit_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
        $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
        $table->string('action', 64)->default('status_change');
        $table->string('from_status', 64)->nullable();
        $table->string('to_status', 64)->nullable();
        $table->text('revision_comment')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->timestamps();

        $table->index(['booking_id', 'created_at']);
        $table->index(['actor_user_id', 'created_at']);
    });

    echo "booking_audit_logs: recreated empty schema; historical rows unavailable\n";
} else {
    echo "booking_audit_logs: no recreate needed\n";
}

// --- vendor_item_sales ---
$salesMigration = '2026_09_21_100002_create_vendor_item_sales_table';
$salesRecorded = DB::table('migrations')->where('migration', $salesMigration)->exists();
$salesUsable = tableUsable('vendor_item_sales');

echo "vendor_item_sales recorded=".($salesRecorded ? '1' : '0')." usable=".($salesUsable ? '1' : '0')."\n";

if ($salesRecorded && ! $salesUsable) {
    try {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('vendor_item_sales');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $e) {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        echo 'vendor_item_sales drop warning: '.$e->getMessage()."\n";
    }

    Schema::create('vendor_item_sales', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('vendor_item_id');
        $table->unsignedBigInteger('vendor_user_id');
        $table->unsignedBigInteger('carboot_event_id');
        $table->unsignedBigInteger('vendor_booking_id')->nullable();
        $table->unsignedBigInteger('item_reservation_id')->nullable();
        $table->string('sale_source', 32);
        $table->string('item_name_snapshot');
        $table->decimal('asking_price_snapshot', 10, 2)->nullable();
        $table->decimal('final_sale_price', 10, 2);
        $table->char('currency', 3)->default('MYR');
        $table->timestamp('sold_at');
        $table->unsignedBigInteger('recorded_by_user_id');
        $table->timestamps();

        $table->unique('vendor_item_id', 'vendor_item_sales_item_unique');
        $table->unique('item_reservation_id', 'vendor_item_sales_reservation_unique');
        $table->index(['vendor_user_id', 'sold_at'], 'vendor_item_sales_vendor_sold_index');
        $table->index(['carboot_event_id', 'sale_source'], 'vendor_item_sales_event_source_index');
    });

    try {
        Schema::table('vendor_item_sales', function (Blueprint $table) {
            $table->foreign('vendor_item_id', 'vendor_item_sales_vendor_item_id_foreign')
                ->references('id')->on('vendor_items')->restrictOnDelete();
            $table->foreign('vendor_user_id', 'vendor_item_sales_vendor_user_id_foreign')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('carboot_event_id', 'vendor_item_sales_carboot_event_id_foreign')
                ->references('id')->on('carboot_events')->restrictOnDelete();
            $table->foreign('vendor_booking_id', 'vendor_item_sales_vendor_booking_id_foreign')
                ->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('item_reservation_id', 'vendor_item_sales_item_reservation_id_foreign')
                ->references('id')->on('item_reservations')->nullOnDelete();
            $table->foreign('recorded_by_user_id', 'vendor_item_sales_recorded_by_user_id_foreign')
                ->references('id')->on('users')->restrictOnDelete();
        });
        echo "vendor_item_sales: foreign keys attached\n";
    } catch (Throwable $e) {
        echo 'vendor_item_sales FK warning: '.$e->getMessage()."\n";
    }

    try {
        DB::statement("ALTER TABLE vendor_item_sales ADD CONSTRAINT vendor_item_sales_source_check CHECK (sale_source IN ('reserved', 'walk_in'))");
    } catch (Throwable) {
    }
    try {
        DB::statement('ALTER TABLE vendor_item_sales ADD CONSTRAINT vendor_item_sales_final_price_non_negative CHECK (final_sale_price >= 0)');
    } catch (Throwable) {
    }

    echo "vendor_item_sales: recreated empty schema; migration record preserved\n";
} else {
    echo "vendor_item_sales: no recreate needed\n";
}

echo "\n== post-reconcile verification ==\n";
foreach (['booking_audit_logs', 'vendor_item_event_selections', 'vendor_item_sales'] as $table) {
    $usable = tableUsable($table);
    $count = $usable ? DB::table($table)->count() : 'n/a';
    echo "{$table}: usable=".($usable ? '1' : '0')." count={$count}\n";
}

$cols = Schema::getColumnListing('booking_audit_logs');
echo 'booking_audit_logs columns='.implode(',', $cols)."\n";
$salesCols = Schema::getColumnListing('vendor_item_sales');
echo 'vendor_item_sales columns='.implode(',', $salesCols)."\n";
