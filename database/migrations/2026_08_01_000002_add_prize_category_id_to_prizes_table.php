<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prizes', function (Blueprint $table) {
            if (! Schema::hasColumn('prizes', 'prize_category_id')) {
                $table->foreignId('prize_category_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('prize_categories')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('prizes', function (Blueprint $table) {
            if (Schema::hasColumn('prizes', 'prize_category_id')) {
                $table->dropConstrainedForeignId('prize_category_id');
            }
        });
    }
};
