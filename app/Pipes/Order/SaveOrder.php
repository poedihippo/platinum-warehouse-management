<?php
namespace App\Pipes\Order;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class SaveOrder
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $salesOrder = DB::transaction(function () use ($salesOrder) {
            $salesOrderDetails = $salesOrder->sales_order_details;
            unset($salesOrder->sales_order_details);

            $salesOrder->save();
            $salesOrder->details()->saveMany($salesOrderDetails);

            return $salesOrder;
        });

        return $next($salesOrder);
    }
}
