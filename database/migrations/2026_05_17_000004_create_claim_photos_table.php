<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Product photos attached to a claim (1+ per claim).
     * cascadeOnDelete with claims is safe: a photo has no meaning
     * without its parent claim, and claims themselves are never
     * cascade-deleted from loyalty_users.
     */
    public function up(): void
    {
        Schema::create('claim_photos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('photo_path');
            $table->integer('position');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_photos');
    }
};
