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

        if (! array_key_exists('voucher_codes', $rawSource)) {
            return $next($salesOrder);
        }

        $codes = $rawSource['voucher_codes'] ?? null;

        if (! is_array($codes) || empty($codes)) {
            $salesOrder->total_voucher = 0;
            $salesOrder->vouchers_ids_to_sync = [];
            $salesOrder->vouchers_discount_amount = [];
            $salesOrder->setRelation('vouchers', collect());

            return $next($salesOrder);
        }

        $vouchers = Voucher::whereIn('code', $codes)
            ->with('category:id,name,discount_type,discount_amount')
            ->get(['id', 'voucher_category_id', 'code']);

        $validVouchers = $vouchers->filter(fn ($v) => $v->isValid());

        if ($validVouchers->isEmpty()) {
            $salesOrder->total_voucher = 0;
            $salesOrder->vouchers_ids_to_sync = [];
            $salesOrder->vouchers_discount_amount = [];
            $salesOrder->setRelation('vouchers', collect());

            return $next($salesOrder);
        }

        $totalVoucherNominal = 0;
        $discountAmountByVoucherId = [];

        foreach ($validVouchers as $voucher) {
            $category = $voucher->category;

            if ($category->discount_type->is(DiscountType::NOMINAL)) {
                $discountAmount = $category->discount_amount;
            } else {
                $discountAmount = $salesOrder->price * $category->discount_amount / 100;
            }

            $actualDiscount = (int) min($discountAmount, $salesOrder->price);
            $salesOrder->price -= $actualDiscount;
            $totalVoucherNominal += $actualDiscount;
            $discountAmountByVoucherId[$voucher->id] = $actualDiscount;
        }

        $rawSource['voucher_total_nominal'] = $totalVoucherNominal;
        $rawSource['voucher_value_nominal_per_voucher'] = array_values($discountAmountByVoucherId);
        $salesOrder->raw_source = $rawSource;
        $salesOrder->total_voucher = (int) $totalVoucherNominal;
        $salesOrder->vouchers_ids_to_sync = $validVouchers->pluck('id')->all();
        $salesOrder->vouchers_discount_amount = $discountAmountByVoucherId;

        // Attach the vouchers with their applied discount to the relation so the
        // response has them even on preview, where the pivot sync never runs.
        $validVouchers->each(fn ($voucher) => $voucher->setRelation(
            'pivot',
            $salesOrder->vouchers()->newPivot([
                'voucher_id' => $voucher->id,
                'discount_amount' => $discountAmountByVoucherId[$voucher->id],
            ])
        ));
        $salesOrder->setRelation('vouchers', $validVouchers->values());

        return $next($salesOrder);
    }
}
