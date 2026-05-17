<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A customer's request to exchange points for a prize.
     *
     * Both FKs restrictOnDelete: a redemption is part of the points
     * audit trail (it has a matching points_transactions row) and must
     * survive deletion of the user or prize catalog row.
     *
     * Note: redemption customer/admin endpoints are out of scope for
     * Phase 1; this table is created now so the schema is complete and
     * points-balance math (which reads spend transactions) is testable.
     */
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('loyalty_user_id')->constrained('loyalty_users')->restrictOnDelete();
            $table->foreignUlid('prize_id')->constrained('prizes')->restrictOnDelete();
            $table->integer('point_cost');
            $table->enum('status', ['pending', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->text('recipient_address');
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
