<?php

use App\Enums\BatchSource;
use App\Models\User;
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
        Schema::create('voucher_generate_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->string('source', 10)->default(BatchSource::UPLOAD);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_generate_batches');
    }
};
