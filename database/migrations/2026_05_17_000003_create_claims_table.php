<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A single invoice submission by a loyalty customer.
     *
     * FK to loyalty_users is restrictOnDelete (NOT cascade): deleting a
     * customer must never silently wipe the claim audit trail.
     * FK to users (reviewer) nullOnDelete: warehouse users are soft-deleted
     * anyway, but if one is hard-deleted we keep the claim, just lose the
     * reviewer pointer.
     */
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('loyalty_user_id')->constrained('loyalty_users')->restrictOnDelete();
            $table->string('invoice_number', 100);
            $table->string('invoice_photo_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->integer('total_points')->default(0);
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
            // Enforces the per-user invoice-number uniqueness fraud rule
            // (spec §9.1): a customer cannot submit the same invoice twice.
            $table->unique(['loyalty_user_id', 'invoice_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
