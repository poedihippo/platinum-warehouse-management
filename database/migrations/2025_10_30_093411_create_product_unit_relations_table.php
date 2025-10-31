<?php

use App\Models\ProductUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_unit_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductUnit::class)->constrained()->cascadeOnDelete();
            $table->integer('related_product_unit_id')->unsigned()->constrained('product_units', 'id');
            $table->smallInteger('qty')->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_unit_relations');
    }
};
