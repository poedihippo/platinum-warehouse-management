<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores the S3 key for a brand's logo image, uploaded via
     * ProductBrandController::update. Null until an admin uploads one.
     *
     * Guarded with Schema::hasColumn, same defensive pattern as
     * 2026_07_13_000001_add_loyalty_eligible_to_product_units_table.php.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('product_brands', 'logo_path')) {
            Schema::table('product_brands', function (Blueprint $table) {
                $table->string('logo_path')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('product_brands', 'logo_path')) {
            Schema::table('product_brands', function (Blueprint $table) {
                $table->dropColumn('logo_path');
            });
        }
    }
};
