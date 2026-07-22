<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split ambiguous feedbacks.reviewer_role into:
 * - participation_type (required for new submissions; how they took part)
 * - community_backgrounds (optional JSON multi-select; institutional/local context)
 *
 * reviewer_role is retained for legacy rows and as a human-readable participation label mirror.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->string('participation_type', 64)->nullable()->after('reviewer_role');
            $table->json('community_backgrounds')->nullable()->after('participation_type');
        });
    }

    public function down(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropColumn(['participation_type', 'community_backgrounds']);
        });
    }
};
