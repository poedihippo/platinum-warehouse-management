<?php

namespace App\Pipes\Order;

use App\Interfaces\Services\DeliveryOrder\DeliveryOrderServiceInterface;
use App\Models\SalesOrder;

class NormalizeLockedOrderItems
{
    public function __construct(protected DeliveryOrderServiceInterface $deliveryOrderService)
    {
    }

    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $this->deliveryOrderService->normalizeLockedItems($salesOrder);

        return $next($salesOrder);
    }
}
