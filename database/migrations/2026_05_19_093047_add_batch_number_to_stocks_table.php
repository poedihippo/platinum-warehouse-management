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
            Schema::table('stocks', function (Blueprint $table) {
                $table->string('batch_number')->nullable()->after('parent_id');
                $table->string('batch_number_jp')->nullable()->after('batch_number');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['batch_number', 'batch_number_jp']);
        });
    }
};
