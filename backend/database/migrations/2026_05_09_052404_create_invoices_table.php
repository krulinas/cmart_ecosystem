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
    Schema::create('invoices', function (Blueprint $table) {
        $table->id(); // invoice_id Primary Key
        $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
        $table->float('amount');
        $table->enum('payment_status', ['Paid', 'Unpaid'])->default('Unpaid');
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
        Schema::dropIfExists('invoices');
    }
};
