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
    Schema::create('bookings', function (Blueprint $table) {
        $table->id(); // booking_id Primary Key
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('space_id')->constrained('spaces')->onDelete('cascade');
        $table->date('booking_date');
        $table->enum('approval_status', [
            'Pending_Staff',
            'Needs_Revision',
            'Pending_Boss',
            'Approved',
            'Rejected',
        ])->default('Pending_Staff');
        $table->text('revision_comment')->nullable();
        $table->string('whatsapp_link')->nullable();
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
        Schema::dropIfExists('bookings');
    }
};
