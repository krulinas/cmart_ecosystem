<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_item_event_selections')) {
            Schema::create('vendor_item_event_selections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_item_id')->constrained('vendor_items')->cascadeOnDelete();
                $table->foreignId('carboot_event_id')->constrained('carboot_events')->cascadeOnDelete();
                $table->foreignId('vendor_booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('vendor_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('selected_at')->useCurrent();
                $table->timestamps();

                $table->unique(
                    ['vendor_item_id', 'carboot_event_id'],
                    'vendor_item_event_selections_item_event_unique',
                );
                $table->index(
                    ['carboot_event_id', 'vendor_user_id'],
                    'vendor_item_event_selections_event_vendor_index',
                );
                $table->index('vendor_booking_id', 'vendor_item_event_selections_booking_index');
            });

            return;
        }

        $existing = collect(DB::select('SHOW INDEX FROM vendor_item_event_selections'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('vendor_item_event_selections', function (Blueprint $table) use ($existing) {
            if (! in_array('vendor_item_event_selections_item_event_unique', $existing, true)) {
                $table->unique(
                    ['vendor_item_id', 'carboot_event_id'],
                    'vendor_item_event_selections_item_event_unique',
                );
            }

            if (! in_array('vendor_item_event_selections_event_vendor_index', $existing, true)) {
                $table->index(
                    ['carboot_event_id', 'vendor_user_id'],
                    'vendor_item_event_selections_event_vendor_index',
                );
            }

            if (! in_array('vendor_item_event_selections_booking_index', $existing, true)) {
                $table->index('vendor_booking_id', 'vendor_item_event_selections_booking_index');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_item_event_selections');
    }
};
