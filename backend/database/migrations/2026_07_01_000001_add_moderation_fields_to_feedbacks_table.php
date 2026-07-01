<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moderation workflow: reviewed status and controlled official CMart reply.
     * Video feedback is intentionally left as future enhancement.
     */
    public function up(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('is_hidden');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();

            $table->text('official_reply_text')->nullable()->after('reviewed_by');
            $table->string('official_reply_status', 20)->nullable()->after('official_reply_text');
            $table->foreignId('official_reply_by')->nullable()->after('official_reply_status')->constrained('users')->nullOnDelete();
            $table->timestamp('official_reply_published_at')->nullable()->after('official_reply_by');
        });
    }

    public function down(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('official_reply_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'reviewed_at',
                'official_reply_text',
                'official_reply_status',
                'official_reply_published_at',
            ]);
        });
    }
};
