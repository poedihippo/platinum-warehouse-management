<?php

namespace App\Pipes\Order;

use App\Models\SalesOrder;

class CalculateAutoDiscount
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $rawSource = $salesOrder->raw_source;
        $salesOrder->auto_discount = 0;
        $minTrxAutoDiscount = config('app.min_trx_auto_discount', []);
        foreach ($minTrxAutoDiscount as $discount) {
            if ($salesOrder->price > $discount['value']) {
                $salesOrder->auto_discount = $discount['discount'] ?? 0;
                return;
            }
        }

        $autoDiscountNominal = 0;
        if ($salesOrder->auto_discount > 0) {
            $autoDiscountNominal = $salesOrder->price * $salesOrder->auto_discount / 100;
            $salesOrder->price = max($salesOrder->price - $autoDiscountNominal, 0);
        }

        $rawSource['auto_discount'] = $salesOrder->auto_discount;
        $rawSource['auto_discount_nominal'] = $autoDiscountNominal;
        $salesOrder->raw_source = $rawSource;

        return $next($salesOrder);
    }
}
