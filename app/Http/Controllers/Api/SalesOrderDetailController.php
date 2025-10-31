<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderDetailResource;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SalesOrderDetailController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission:sales_order_access', ['only' => ['index', 'show']]);
    }

    public function index(int $salesOrderId)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('sales_order_access'), 403);

        $salesOrder = SalesOrder::findTenanted($salesOrderId, ['id']);
        $query = SalesOrderDetail::where('sales_order_id', $salesOrder->id)->with([
            // 'packaging',
            'warehouse' => fn($q) => $q->select('id', 'code', 'name')
        ]);
        $salesOrderDetails = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::scope('has_delivery_order')
            ])->get();

        return SalesOrderDetailResource::collection($salesOrderDetails);
    }

    public function show(int $salesOrderId, $salesOrderDetailId)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('sales_order_access'), 403);

        $salesOrder = SalesOrder::findTenanted($salesOrderId, ['id']);
        $salesOrderDetail = $salesOrder->details()->where('id', $salesOrderDetailId)->firstOrFail();

        return new SalesOrderDetailResource($salesOrderDetail->load(['warehouse' => fn($q) => $q->select('id', 'code', 'name')]));
    }
}
