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
        if (! Schema::hasColumn('product_units', 'multiply_qty')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->unsignedInteger('multiply_qty')->default(1)->after('refer_qty');
            });
        }

        if (! Schema::hasColumn('sales_order_details', 'multiply_qty')) {
            Schema::table('sales_order_details', function (Blueprint $table) {
                $table->unsignedInteger('multiply_qty')->default(1)->after('qty');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sales_order_details', 'multiply_qty')) {
            Schema::table('sales_order_details', function (Blueprint $table) {
                $table->dropColumn('multiply_qty');
            });
        }

        if (Schema::hasColumn('product_units', 'multiply_qty')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->dropColumn('multiply_qty');
            });
        }
    }
};
