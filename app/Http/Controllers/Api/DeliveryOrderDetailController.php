<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryOrderDetailResource;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use Spatie\QueryBuilder\QueryBuilder;

class DeliveryOrderDetailController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:delivery_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:delivery_order_delete', ['only' => 'destroy']);
    }

    public function index(DeliveryOrder $deliveryOrder)
    {
        // abort_if(!auth()->user()->tokenCan('delivery_order_access'), 403);
        $deliveryOrderDetails = QueryBuilder::for(DeliveryOrderDetail::with(['salesOrderDetail' => fn ($q) => $q->with('warehouse', 'salesOrder')])->where('delivery_order_id', $deliveryOrder->id))
            ->allowedFilters([
                'delivery_order_id',
                'sales_order_detail_id',
            ])
            ->allowedSorts(['id', 'delivery_order_id', 'sales_order_detail_id', 'created_at'])
            ->paginate();

        return DeliveryOrderDetailResource::collection($deliveryOrderDetails);
    }

    public function show(DeliveryOrder $deliveryOrder, $deliveryOrderDetailId)
    {
        // abort_if(!auth()->user()->tokenCan('delivery_order_access'), 403);
        $deliveryOrderDetail = $deliveryOrder->details()->where('id', $deliveryOrderDetailId)->firstOrFail();

        $deliveryOrderDetail->load([
            'deliveryOrder',
            'salesOrderDetail' => function ($q) {
                $q->with(['warehouse', 'salesOrder']);
            }
        ]);

        return new DeliveryOrderDetailResource($deliveryOrderDetail);
    }

    public function destroy(DeliveryOrder $deliveryOrder, $deliveryOrderDetailId)
    {
        abort_if(!auth()->user()->tokenCan('delivery_order_delete'), 403);

        $deliveryOrder->details()->where('id', $deliveryOrderDetailId)->delete();

        return $this->deletedResponse();
    }
}
