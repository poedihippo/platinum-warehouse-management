<?php

namespace App\Services;

use App\Models\SalesOrderDetail;

class SalesOrderService
{
    public static function countFulfilledQty(SalesOrderDetail $salesOrderDetail)
    {
        $salesOrderDetail->update([
            'fulfilled_qty' => $salesOrderDetail->salesOrderItems->count()
        ]);
    }
}