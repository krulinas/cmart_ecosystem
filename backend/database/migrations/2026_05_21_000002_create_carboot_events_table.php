<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carboot_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('Available');
            $table->text('description')->nullable();
            $table->unsignedInteger('max_slots')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carboot_events');
    }
};
