<?php

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
        Schema::create('voucher_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('discount_type', ['nominal', 'percentage']);
            $table->float('discount_amount', 11, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            // softDeletes must implement deleted_by
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_categories');
    }
};
