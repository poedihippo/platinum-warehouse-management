<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_details', function (Blueprint $table) {
            $table->index('sales_order_id');
        });

        Schema::table('delivery_order_details', function (Blueprint $table) {
            $table->index('delivery_order_id');
            $table->index('sales_order_detail_id');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->index('sales_order_detail_id');
            $table->index('delivery_order_detail_id');
            $table->index('stock_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_details', function (Blueprint $table) {
            $table->dropIndex(['sales_order_id']);
        });

        Schema::table('delivery_order_details', function (Blueprint $table) {
            $table->dropIndex(['delivery_order_id']);
            $table->dropIndex(['sales_order_detail_id']);
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropIndex(['sales_order_detail_id']);
            $table->dropIndex(['delivery_order_detail_id']);
            $table->dropIndex(['stock_id']);
            $table->dropIndex(['parent_id']);
        });
    }
};
