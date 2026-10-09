<?php

namespace App\Pipes\Order;

use App\Models\SalesOrder;
use App\Services\DeliveryOrderService;

class NormalizeLockedOrderItems
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        DeliveryOrderService::normalizeLockedItems($salesOrder);

        return $next($salesOrder);
    }
}
