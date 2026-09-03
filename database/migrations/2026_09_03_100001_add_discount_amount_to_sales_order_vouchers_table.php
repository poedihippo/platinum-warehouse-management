<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_order_vouchers', 'discount_amount')) {
            Schema::table('sales_order_vouchers', function (Blueprint $table) {
                $table->unsignedInteger('discount_amount')->default(0)->after('voucher_id');
            });
        }

        // Backfill applied discount for existing (legacy) pivot rows. Raw_source for
        // these orders predates the per-voucher nominal keys, so the applied nominal
        // is recomputed from the stored final price:
        //   - NOMINAL: the voucher's fixed discount_amount (exact).
        //   - PERCENTAGE (p): final_price * p / (100 - p) — the discount that, when
        //     added to the final price, equals the pre-voucher price (single voucher).
        $vouchers = DB::table('vouchers as v')
            ->join('voucher_categories as vc', 'vc.id', '=', 'v.voucher_category_id')
            ->select('v.id', 'vc.discount_type', 'vc.discount_amount')
            ->get()
            ->keyBy('id');

        $rows = DB::table('sales_order_vouchers as sov')
            ->join('sales_orders as so', 'so.id', '=', 'sov.sales_order_id')
            ->where('sov.discount_amount', 0)
            ->select('sov.sales_order_id', 'sov.voucher_id', 'so.price')
            ->get();

        foreach ($rows as $row) {
            $voucher = $vouchers->get($row->voucher_id);

            if (! $voucher) {
                continue;
            }

            $discountAmount = $voucher->discount_type === 'nominal'
                ? $voucher->discount_amount
                : (int) round($row->price * $voucher->discount_amount / (100 - $voucher->discount_amount));

            DB::table('sales_order_vouchers')
                ->where('sales_order_id', $row->sales_order_id)
                ->where('voucher_id', $row->voucher_id)
                ->update(['discount_amount' => $discountAmount]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_order_vouchers', 'discount_amount')) {
            Schema::table('sales_order_vouchers', function (Blueprint $table) {
                $table->dropColumn('discount_amount');
            });
        }
    }
};
