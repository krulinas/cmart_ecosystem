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
    Schema::create('feedbacks', function (Blueprint $table) {
        $table->id(); // feedback_id Primary Key
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->text('comments');
        $table->integer('rating');
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
        Schema::dropIfExists('feedbacks');
    }
};
