<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['sales_order_id', 'voucher_id']);
        });

        // Migrate existing voucher_id from sales_orders
        DB::table('sales_order_vouchers')->insertUsing(
            ['sales_order_id', 'voucher_id', 'created_at', 'updated_at'],
            DB::table('sales_orders')->whereNotNull('voucher_id')->select(
                'id',
                'voucher_id',
                DB::raw('NOW() as created_at'),
                DB::raw('NOW() as updated_at')
            )
        );

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn('voucher_id');
        });

        DB::statement('ALTER TABLE sales_orders ADD CONSTRAINT sales_orders_price_non_negative CHECK (price >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sales_orders DROP CONSTRAINT sales_orders_price_non_negative');

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('voucher_id')->nullable()->after('user_id');
            $table->foreign('voucher_id')->references('id')->on('vouchers');
        });

        // Migrate back: take the first voucher per SO
        DB::statement('UPDATE sales_orders s
            JOIN sales_order_vouchers sov ON sov.sales_order_id = s.id
            SET s.voucher_id = sov.voucher_id
            WHERE sov.id = (
                SELECT MIN(sov2.id) FROM sales_order_vouchers sov2 WHERE sov2.sales_order_id = s.id
            )');

        Schema::dropIfExists('sales_order_vouchers');
    }
};
