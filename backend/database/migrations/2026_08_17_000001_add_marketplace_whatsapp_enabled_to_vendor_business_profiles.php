<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_business_profiles', function (Blueprint $table) {
            $table->boolean('marketplace_whatsapp_enabled')
                ->default(false)
                ->after('business_phone');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_business_profiles', function (Blueprint $table) {
            $table->dropColumn('marketplace_whatsapp_enabled');
        });
    }
};
