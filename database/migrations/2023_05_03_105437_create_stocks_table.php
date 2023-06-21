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
        Schema::create('stocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('parent_id')->nullable()->constrained('stocks', 'id');
            $table->foreignId('stock_product_unit_id')->constrained();
            $table->foreignId('adjustment_request_id')->nullable()->index();
            // $table->foreignId('product_unit_id')->constrained();
            // $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('receive_order_id')->nullable()->constrained();
            $table->foreignId('receive_order_detail_id')->nullable()->constrained();
            $table->string('description')->nullable();
            $table->text('qr_code')->nullable();
            $table->integer('scanned_count')->default(0);
            $table->dateTime('scanned_datetime')->nullable();
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
        Schema::dropIfExists('stocks');
    }
};
