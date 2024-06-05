<?php
namespace App\Pipes\Order;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class UpdateOrder
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $salesOrder = DB::transaction(function () use ($salesOrder) {
            $oldDetails = $salesOrder->details;

            $salesOrderDetails = $salesOrder->details;
            unset($salesOrder->details);

            $salesOrder->save();
            $salesOrder->details()->saveMany($salesOrderDetails);

            $oldDetails->each->delete();

            return $salesOrder;
        });

        return $next($salesOrder);
    }
}
