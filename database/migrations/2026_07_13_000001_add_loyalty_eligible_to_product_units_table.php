<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Marks which product units may ever be added to the loyalty points
     * program. Default false = a unit must be explicitly curated in
     * before an admin can set points_per_unit on it (see
     * LoyaltyEligibleProductUnitSeeder).
     *
     * Guarded with Schema::hasColumn, same defensive pattern as
     * 2026_05_17_000002_add_points_per_unit_to_product_units_table.php.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('product_units', 'loyalty_eligible')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->boolean('loyalty_eligible')->default(false)->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('product_units', 'loyalty_eligible')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->dropColumn('loyalty_eligible');
            });
        }
    }
};
