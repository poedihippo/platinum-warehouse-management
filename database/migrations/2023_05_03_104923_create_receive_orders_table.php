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
        Schema::create('receive_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->foreignId('warehouse_id')->nullable()->constrained();

            // data xml
            $table->string('invoice_no')->nullable()->unique();
            $table->date('invoice_date')->nullable();
            $table->integer('invoice_amount')->default(0);
            $table->string('purchase_order_no')->nullable();
            $table->string('warehouse_string_id')->nullable();
            $table->string('vendor_id')->nullable();
            $table->string('sequence_no')->nullable();
            // data xml

            // $table->string('name');
            // $table->text('description');
            $table->dateTime('receive_datetime');
            $table->boolean('is_done')->default(0);
            $table->timestamp('done_at')->nullable();
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
        Schema::dropIfExists('receive_orders');
    }
};
