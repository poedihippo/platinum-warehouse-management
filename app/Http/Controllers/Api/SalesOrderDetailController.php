<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderDetailResource;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use Spatie\QueryBuilder\QueryBuilder;

class SalesOrderDetailController extends Controller
{
    public function index(SalesOrder $salesOrder)
    {
        $query = SalesOrderDetail::where('sales_order_id', $salesOrder->id);
        $salesOrderDetails = QueryBuilder::for($query)
            ->paginate();

        return SalesOrderDetailResource::collection($salesOrderDetails);
    }

    public function show(SalesOrder $salesOrder, $salesOrderDetailId)
    {
        $salesOrderDetail = $salesOrder->details()->where('id', $salesOrderDetailId)->firstOrFail();

        return new SalesOrderDetailResource($salesOrderDetail);
    }
}
