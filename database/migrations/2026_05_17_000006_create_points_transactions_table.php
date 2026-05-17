<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only points ledger. Balance is always computed from this
     * table, never stored (spec §5.9).
     *
     * source_type/source_id are an application-level polymorphic pointer
     * (values: 'claim' or 'redemption'). Deliberately NOT a Laravel
     * morphTo / FK because the two source models live in different
     * namespaces and we never want a cascade to mutate the ledger.
     *
     * loyalty_user_id restrictOnDelete: the ledger is an audit record.
     */
    public function up(): void
    {
        Schema::create('points_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('loyalty_user_id')->constrained('loyalty_users')->restrictOnDelete();
            $table->enum('direction', ['earn', 'spend']);
            $table->integer('amount');
            $table->string('source_type');
            $table->ulid('source_id');
            $table->string('description');
            $table->timestamp('created_at')->nullable();

            $table->index(['loyalty_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_transactions');
    }
};
