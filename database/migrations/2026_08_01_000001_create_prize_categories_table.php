<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Prize category catalog. Soft-deleted, matching ProductBrand's
     * convention — a deleted category's prizes fall back to "prize_category_id
     * set but the category itself invisible", which the app treats as
     * uncategorized rather than orphaning the FK.
     */
    public function up(): void
    {
        Schema::create('prize_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prize_categories');
    }
};
