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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('reseller_id')->constrained('users', 'id');
            $table->foreignId('warehouse_id')->constrained();
            $table->string('code', 50);
            $table->string('invoice_no', 20);
            $table->string('status', 20);
            $table->integer('price')->default(0);
            $table->dateTime('transaction_date');
            $table->dateTime('shipment_estimation_datetime');
            $table->text('note')->nullable();
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
        Schema::dropIfExists('sales_orders');
    }
};
