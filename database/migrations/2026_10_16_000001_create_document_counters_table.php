<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->string('company', 10);
            $table->string('document_type', 10);
            $table->char('period', 7);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique(['company', 'document_type', 'period'], 'document_counters_company_type_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
    }
};
