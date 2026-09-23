<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_reports', function (Blueprint $table) {
            $table->text('programme_introduction')->nullable()->after('organizer_recommendations');
            $table->json('programme_objectives')->nullable()->after('programme_introduction');
            $table->boolean('objectives_not_applicable')->default(false)->after('programme_objectives');
            $table->text('conclusion')->nullable()->after('objectives_not_applicable');
        });
    }

    public function down(): void
    {
        Schema::table('generated_reports', function (Blueprint $table) {
            $table->dropColumn([
                'programme_introduction',
                'programme_objectives',
                'objectives_not_applicable',
                'conclusion',
            ]);
        });
    }
};
