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
        Schema::create('sales_order_details', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained();
            $table->foreignId('product_unit_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->integer('qty')->default(0);
            $table->integer('fulfilled_qty')->default(0);
            $table->integer('unit_price')->default(0);
            $table->integer('discount')->default(0);
            $table->integer('tax')->default(0);
            $table->integer('total_price')->default(0);
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
        Schema::dropIfExists('sales_order_details');
    }
};
