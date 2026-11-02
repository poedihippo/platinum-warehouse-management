<?php

namespace App\Http\Repositories\SalesOrder;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\SalesOrder\SalesOrderRepositoryInterface;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;

class SalesOrderRepository extends BaseRepository implements SalesOrderRepositoryInterface
{
    public function __construct(SalesOrder $model)
    {
        parent::__construct($model);
    }

    /**
     * fulfilled_qty = scanned items of the detail that are neither group parents nor returned.
     */
    public function countFulfilledQty(SalesOrderDetail|int $salesOrderDetail): void
    {
        if (! $salesOrderDetail instanceof SalesOrderDetail) {
            $salesOrderDetail = SalesOrderDetail::find($salesOrderDetail);
            if (! $salesOrderDetail) {
                return;
            }
        }

        $salesOrderDetail->refresh();

        $salesOrderDetail->update([
            'fulfilled_qty' => $salesOrderDetail->salesOrderItems()->where('is_parent', 0)->where('is_returned', 0)->count(),
        ]);
    }
}
