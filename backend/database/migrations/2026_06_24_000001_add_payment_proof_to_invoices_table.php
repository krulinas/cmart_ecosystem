<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY payment_status ENUM(
            'Paid',
            'Unpaid',
            'Pending Verification',
            'Refunded'
        ) NOT NULL DEFAULT 'Unpaid'");

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'payment_proof_path')) {
                $table->string('payment_proof_path')->nullable()->after('payment_status');
            }

            if (!Schema::hasColumn('invoices', 'payment_submitted_at')) {
                $table->timestamp('payment_submitted_at')->nullable()->after('payment_proof_path');
            }
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE invoices SET payment_status = 'Unpaid' WHERE payment_status = 'Pending Verification'");

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'payment_submitted_at')) {
                $table->dropColumn('payment_submitted_at');
            }

            if (Schema::hasColumn('invoices', 'payment_proof_path')) {
                $table->dropColumn('payment_proof_path');
            }
        });

        DB::statement("ALTER TABLE invoices MODIFY payment_status ENUM('Paid', 'Unpaid') NOT NULL DEFAULT 'Unpaid'");
    }
};
