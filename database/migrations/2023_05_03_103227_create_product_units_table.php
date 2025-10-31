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
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('refer_id')->nullable(); // refer to id
            // $table->unsignedInteger('packaging_id')->nullable();
            $table->integer('uom_id')->foreign()->references('id')->on('uoms');
            $table->string('name', 100);
            $table->integer('price')->default(0);
            $table->text('description');
            $table->string('code', 50);
            $table->boolean('is_generate_qr')->default(1);
            $table->boolean('is_auto_tempel')->default(1);
            $table->boolean('is_ppn')->default(0);
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
        Schema::dropIfExists('product_units');
    }
};
