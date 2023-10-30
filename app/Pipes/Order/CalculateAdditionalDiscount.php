<?php
namespace App\Pipes\Order;

use App\Models\SalesOrder;

class CalculateAdditionalDiscount
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        if ($salesOrder->additional_discount > 0) {
            $salesOrder->additional_discount = $salesOrder->price * $salesOrder->additional_discount / 100;
            $salesOrder->price = max($salesOrder->price - $salesOrder->additional_discount, 0);
        }

        return $next($salesOrder);
    }
}
