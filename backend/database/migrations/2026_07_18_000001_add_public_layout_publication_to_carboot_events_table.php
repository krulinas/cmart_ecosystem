<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carboot_events', function (Blueprint $table) {
            $table->timestamp('public_layout_published_at')->nullable()->after('day_generation_mode');
            $table->text('public_layout_entrance_note')->nullable()->after('public_layout_published_at');
        });
    }

    public function down(): void
    {
        Schema::table('carboot_events', function (Blueprint $table) {
            $table->dropColumn([
                'public_layout_published_at',
                'public_layout_entrance_note',
            ]);
        });
    }
};
