<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderDetailResource;
use App\Models\Order;
use App\Models\OrderDetail;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrderDetailController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission:sales_order_access', ['only' => ['index', 'show']]);
    }

    public function index(int $orderId)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('sales_order_access'), 403);

        $order = Order::findTenanted($orderId, ['id']);
        $query = OrderDetail::where('sales_order_id', $order->id)->with([
            'packaging',
            'warehouse' => fn($q) => $q->select('id', 'code', 'name')
        ]);
        $orderDetails = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::scope('has_delivery_order')
            ])->get();

        return OrderDetailResource::collection($orderDetails);
    }

    public function show(int $orderId, $orderDetailId)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('sales_order_access'), 403);

        $order = Order::findTenanted($orderId, ['id']);
        $orderDetail = $order->details()->where('id', $orderDetailId)->firstOrFail();

        return new OrderDetailResource($orderDetail->load(['warehouse' => fn($q) => $q->select('id', 'code', 'name')]));
    }
}
