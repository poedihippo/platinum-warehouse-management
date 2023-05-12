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
            $table->id();
            $table->foreignId('product_unit_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('receive_order_id')->constrained()->cascadeOnDelete();
            $table->text('qr_code')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users');
            $table->dateTime('scanned_datetime')->nullable();
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
        Schema::dropIfExists('stocks');
    }
};
