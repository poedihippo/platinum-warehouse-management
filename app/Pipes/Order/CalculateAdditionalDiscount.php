<?php
namespace App\Pipes\Order;

use App\Models\SalesOrder;

class CalculateAdditionalDiscount
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        if ($salesOrder->additional_discount > 0) {
            $salesOrder->price = $salesOrder->price - ($salesOrder->price * $salesOrder->additional_discount / 100);
        }

        return $next($salesOrder);
    }
}
