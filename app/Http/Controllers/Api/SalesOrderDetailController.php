<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderDetailResource;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class SalesOrderDetailController extends Controller
{
    public function index(SalesOrder $salesOrder)
    {
        $salesOrderDetails = QueryBuilder::for(SalesOrderDetail::class)
            ->paginate();
        return SalesOrderDetailResource::collection($salesOrderDetails);
    }

    public function show(SalesOrder $salesOrder, $salesOrderDetailId)
    {
        $salesOrderDetail = $salesOrder->details()->where('id', $salesOrderDetailId)->firstOrFail();

        return new SalesOrderDetailResource($salesOrderDetail);
    }
}
