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
            $table->string('invoice_no', 20)->nullable();
            // $table->string('status', 20);
            $table->json('raw_source')->nullable();
            $table->json('records')->nullable();
            $table->integer('shipment_fee')->default(0);
            $table->integer('additional_discount')->default(0);
            $table->integer('price')->default(0);
            $table->dateTime('transaction_date');
            $table->dateTime('shipment_estimation_datetime');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
