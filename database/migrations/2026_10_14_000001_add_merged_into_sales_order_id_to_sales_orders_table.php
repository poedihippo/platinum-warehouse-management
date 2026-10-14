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
        if (Schema::hasColumn('sales_orders', 'merged_into_sales_order_id')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('merged_into_sales_order_id')->nullable()->after('marketplace');
            $table->foreign('merged_into_sales_order_id')
                ->references('id')
                ->on('sales_orders')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('sales_orders', 'merged_into_sales_order_id')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['merged_into_sales_order_id']);
            $table->dropColumn('merged_into_sales_order_id');
        });
    }
};
