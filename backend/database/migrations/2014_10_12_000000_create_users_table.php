<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // Carboot@CMart RBAC: community users can later unlock vendor tools via vendor_status.
            $table->string('phone_number')->nullable();
            $table->enum('role', ['community', 'cmart_staff', 'cmart_admin', 'uum'])->default('community');
            $table->enum('vendor_status', ['none', 'pending', 'approved', 'suspended'])->default('none');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
