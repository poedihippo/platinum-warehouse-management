<?php

namespace App\Services;

use App\Models\SalesOrderDetail;

class SalesOrderService
{
    public static function countFulfilledQty(SalesOrderDetail $salesOrderDetail)
    {
        $salesOrderDetail->refresh();

        $salesOrderDetail->update([
            'fulfilled_qty' => $salesOrderDetail->salesOrderItems->count()
        ]);
    }
}
