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
        Schema::create('adjustment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('stock_product_unit_id')->constrained();
            $table->boolean('is_increment')->default(1);
            $table->unsignedInteger('value')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_approved')->nullable();
            $table->integer('approved_by')->nullable()->index();
            $table->timestamp('approved_datetime')->nullable();
            $table->text('reason')->nullable();
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
        Schema::dropIfExists('adjustment_requests');
    }
};
