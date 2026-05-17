<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the loyalty points value to each product unit variant.
     * Default 0 = awards no points until an admin sets a value, so
     * existing products do not accidentally earn points.
     *
     * Guarded with Schema::hasColumn so it is a no-op if the column was
     * ever added out-of-band (matches the defensive pattern used by
     * 2026_04_26_120000_add_expired_date_to_stocks_table.php).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('product_units', 'points_per_unit')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->integer('points_per_unit')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('product_units', 'points_per_unit')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->dropColumn('points_per_unit');
            });
        }
    }
};
