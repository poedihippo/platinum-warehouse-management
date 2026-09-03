<?php

namespace App\Pipes\Order;

use App\Enums\DiscountType;
use App\Models\SalesOrder;
use App\Models\Voucher;

class CalculateVoucher
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $rawSource = $salesOrder->raw_source;
        $codes = $rawSource['voucher_codes'] ?? null;

        if (! is_array($codes) || empty($codes)) {
            return $next($salesOrder);
        }

        $vouchers = Voucher::whereIn('code', $codes)
            ->with('category:id,discount_type,discount_amount')
            ->get(['id', 'voucher_category_id', 'code']);

        $validVouchers = $vouchers->filter(fn ($v) => $v->isValid());

        if ($validVouchers->isEmpty()) {
            return $next($salesOrder);
        }

        $totalVoucherNominal = 0;
        $perVoucherNominal = [];

        foreach ($validVouchers as $voucher) {
            $category = $voucher->category;

            if ($category->discount_type->is(DiscountType::NOMINAL)) {
                $discountAmount = $category->discount_amount;
            } else {
                $discountAmount = $salesOrder->price * $category->discount_amount / 100;
            }

            $actualDiscount = min($discountAmount, $salesOrder->price);
            $salesOrder->price -= $actualDiscount;
            $totalVoucherNominal += $actualDiscount;
            $perVoucherNominal[] = $actualDiscount;
        }

        $rawSource['voucher_total_nominal'] = $totalVoucherNominal;
        $rawSource['voucher_value_nominal_per_voucher'] = $perVoucherNominal;
        $salesOrder->raw_source = $rawSource;
        $salesOrder->vouchers_ids_to_sync = $validVouchers->pluck('id')->all();

        return $next($salesOrder);
    }
}
