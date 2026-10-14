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
        if (! Schema::hasColumn('delivery_orders', 'sales_order_id')) {
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('sales_order_id')->nullable()->after('reseller_id');

                $table->index('sales_order_id');

                $table->foreign('sales_order_id')
                    ->references('id')
                    ->on('sales_orders')
                    ->nullOnDelete();
            });
        }

        $this->backfillSalesOrderId();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('delivery_orders', 'sales_order_id')) {
            return;
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
            $table->dropColumn('sales_order_id');
        });
    }

    /**
     * Link the auto-generated Delivery Orders of marketplace Sales Orders to their source.
     *
     * Only Delivery Orders that reference exactly one Sales Order (and that Sales Order is a
     * marketplace order) are considered auto-generated. Manual Delivery Orders are left null.
     */
    private function backfillSalesOrderId(): void
    {
        DB::statement(<<<'SQL'
            UPDATE delivery_orders AS d
            JOIN (
                SELECT dod.delivery_order_id, MIN(sod.sales_order_id) AS sales_order_id
                FROM delivery_order_details dod
                JOIN sales_order_details sod ON sod.id = dod.sales_order_detail_id
                JOIN sales_orders so ON so.id = sod.sales_order_id
                GROUP BY dod.delivery_order_id
                HAVING COUNT(DISTINCT sod.sales_order_id) = 1
                   AND SUM(CASE WHEN so.marketplace IS NOT NULL THEN 1 ELSE 0 END) > 0
            ) AS source ON source.delivery_order_id = d.id
            SET d.sales_order_id = source.sales_order_id
            WHERE d.sales_order_id IS NULL
        SQL);
    }
};
