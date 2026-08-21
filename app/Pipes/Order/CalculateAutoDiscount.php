<?php

namespace App\Pipes\Order;

use App\Models\SalesOrder;

class CalculateAutoDiscount
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $rawSource = $salesOrder->raw_source;
        $salesOrder->auto_discount = 0;

        $originalPrice = $salesOrder->price;
        $discounts = $this->getMatchedDiscounts($originalPrice);

        foreach ($discounts as $discount) {
            $salesOrder->price = max($salesOrder->price - ($salesOrder->price * $discount / 100), 0);
        }

        $autoDiscountNominal = $originalPrice - $salesOrder->price;
        if ($originalPrice > 0 && $autoDiscountNominal > 0) {
            $salesOrder->auto_discount = round($autoDiscountNominal / $originalPrice * 100, 2);
        }

        $rawSource['auto_discount'] = $salesOrder->auto_discount;
        $rawSource['auto_discount_nominal'] = $autoDiscountNominal;
        $salesOrder->raw_source = $rawSource;

        return $next($salesOrder);
    }

    private function getMatchedDiscounts(int|float $price): array
    {
        $tier = collect(config('app.min_trx_auto_discount', []))
            ->first(fn (array $tier) => $price >= $tier['min_value']
                && ($tier['max_value'] === null || $price <= $tier['max_value']));

        return $tier['discount'] ?? [];
    }
}
