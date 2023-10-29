<?php
namespace App\Pipes\Order;
use App\Models\SalesOrder;

class CalculateAdditionalFees
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $salesOrderDetailsTotalPrice = $salesOrder->details->sum('total_price') ?? 0;
        $salesOrderDetailsTotalPrice += $salesOrder->shipment_fee;

        $salesOrder->price = $salesOrderDetailsTotalPrice;

        return $next($salesOrder);
    }
}
