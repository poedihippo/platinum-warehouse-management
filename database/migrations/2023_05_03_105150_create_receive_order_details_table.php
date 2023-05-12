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
        Schema::create('receive_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receive_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained();
            $table->integer('qty')->default(0);
            $table->string('item_unit', 50);
            $table->integer('bruto_unit_price')->default(0);
            $table->integer('adjust_qty')->default(0);
            $table->foreignId('uom_id')->nullable()->constrained();
            $table->unsignedTinyInteger('is_package')->default(0);
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
        Schema::dropIfExists('receive_order_details');
    }
};
