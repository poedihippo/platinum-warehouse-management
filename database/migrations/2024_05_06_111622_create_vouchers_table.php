<?php

use App\Models\VoucherCategory;
use App\Models\VoucherGenerateBatch;
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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(VoucherGenerateBatch::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(VoucherCategory::class)->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
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
        Schema::dropIfExists('vouchers');
    }
};
