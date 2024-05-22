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
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('reseller_id')->constrained('users', 'id');
            // $table->foreignId('sales_order_id')->unique()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('invoice_no', 50)->nullable();
            $table->dateTime('transaction_date');
            $table->dateTime('shipment_estimation_datetime');
            $table->text('description')->nullable();
            $table->boolean('is_done')->default(0);
            $table->timestamp('done_at')->nullable();
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
        Schema::dropIfExists('delivery_orders');
    }
};
