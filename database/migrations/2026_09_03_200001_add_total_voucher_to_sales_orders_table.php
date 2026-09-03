<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_orders', 'total_voucher')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->unsignedInteger('total_voucher')->default(0)->after('additional_discount');
            });
        }

        DB::statement(
            'UPDATE sales_orders s
                SET s.total_voucher = (
                    SELECT COALESCE(SUM(sov.discount_amount), 0)
                    FROM sales_order_vouchers sov
                    WHERE sov.sales_order_id = s.id
                )
                WHERE s.total_voucher = 0'
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_orders', 'total_voucher')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('total_voucher');
            });
        }
    }
};
