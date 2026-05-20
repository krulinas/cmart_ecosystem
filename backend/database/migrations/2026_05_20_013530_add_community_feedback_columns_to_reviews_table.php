<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            // user_id already exists on feedbacks (see create_feedbacks_table)

            $table->tinyInteger('service_rating')->default(0)->after('rating');
            $table->tinyInteger('value_rating')->default(0)->after('service_rating');
            $table->string('media_path')->nullable()->after('value_rating');
            $table->unsignedInteger('helpful_count')->default(0)->after('media_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropColumn([
                'service_rating',
                'value_rating',
                'media_path',
                'helpful_count',
            ]);
        });
    }
};
