<?php
namespace App\Pipes\Order;
use App\Models\SalesOrder;

class CalculateAdditionalFees
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $salesOrder->price += $salesOrder->shipment_fee;

        return $next($salesOrder);
    }
}
