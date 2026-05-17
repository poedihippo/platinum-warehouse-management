<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Products the admin identified inside a claim during review.
     *
     * claim_id cascadeOnDelete: a line item has no meaning without its claim.
     * product_unit_id restrictOnDelete: never destroy the historical link
     * to the awarded product (points_awarded is captured at approval time
     * so values are frozen, but the FK should not be cascaded away).
     */
    public function up(): void
    {
        Schema::create('claim_line_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->integer('quantity');
            $table->integer('points_awarded')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_line_items');
    }
};
