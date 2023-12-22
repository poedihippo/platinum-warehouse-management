<?php
namespace App\Pipes\Order;

use App\Models\SalesOrder;
use Exception;

class CheckExpectedOrderPrice
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        if (!$salesOrder->expected_price) {
            return $next($salesOrder);
        }

        if ($salesOrder->price != $salesOrder->expected_price) {
            throw new Exception("Harga tidak cocok");
        }

        return $next($salesOrder);
    }
}
