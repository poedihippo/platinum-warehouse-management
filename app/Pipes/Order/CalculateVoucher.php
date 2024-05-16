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
        if ($voucherCode = $rawSource['voucher_code'] ?? null) {
            $voucher = Voucher::where('code', $voucherCode)->with('category', fn ($q) => $q->select('id', 'discount_type', 'discount_amount'))->first(['id', 'voucher_category_id']);
            if (!$voucher) return $next($salesOrder);

            $discountVoucherAmount = 0;
            if ($voucher->category->discount_type->is(DiscountType::NOMINAL)) {
                $discountVoucherAmount = $voucher->category->discount_amount;
            } else {
                $originalPrice = $salesOrder->sales_order_details->sum('total_price') ?? 0;
                $discountVoucherAmount = $originalPrice * $voucher->category->discount_amount / 100;
            }

            $salesOrder->price = max($salesOrder->price - $discountVoucherAmount, 0);
            $salesOrder->voucher_id = $voucher->id;

            $rawSource['voucher_value'] = $discountVoucherAmount;
            $salesOrder->raw_source = $rawSource;
        }

        return $next($salesOrder);
    }
}
