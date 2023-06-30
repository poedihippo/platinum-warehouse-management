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
        Schema::create('stock_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->unsignedInteger('user_id')->index();
            $table->foreignId('stock_product_unit_id')->constrained();
            $table->unsignedInteger('value')->value(0);
            $table->boolean('is_increment')->value(1);
            $table->text('description')->nullable();
            $table->string('ip', 30)->nullable();
            $table->string('agent', 30)->nullable();
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
        Schema::dropIfExists('stock_histories');
    }
};
