<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('sales_order_details', 'product_multiply_qty')) {
            return;
        }

        Schema::table('sales_order_details', function (Blueprint $table) {
            $table->unsignedInteger('product_multiply_qty')->default(1)->after('multiply_qty');
        });

        DB::table('sales_order_details')->update(['product_multiply_qty' => DB::raw('multiply_qty')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sales_order_details', 'product_multiply_qty')) {
            Schema::table('sales_order_details', function (Blueprint $table) {
                $table->dropColumn('product_multiply_qty');
            });
        }
    }
};
