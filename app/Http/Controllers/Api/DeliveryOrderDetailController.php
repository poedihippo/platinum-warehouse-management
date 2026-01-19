<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryOrderDetailResource;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DeliveryOrderDetailController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission:delivery_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:delivery_order_delete', ['only' => ['destroy', 'reset']]);
    }

    public function index(int $deliveryOrderId)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('delivery_order_access'), 403);
        $deliveryOrder = DeliveryOrder::findTenanted($deliveryOrderId, ['id']);
        // $deliveryOrderDetails = QueryBuilder::for(DeliveryOrderDetail::with(['salesOrderDetail' => fn($q) => $q->with('warehouse', 'salesOrder', 'packaging')])->where('delivery_order_id', $deliveryOrder->id))
        $deliveryOrderDetails = QueryBuilder::for(DeliveryOrderDetail::with(['salesOrderDetail' => fn($q) => $q->with(['warehouse', 'salesOrder', 'productUnit' => fn($q) => $q->withTrashed()])])->where('delivery_order_id', $deliveryOrder->id))
            ->allowedFilters([
                AllowedFilter::exact('delivery_order_id'),
                AllowedFilter::exact('sales_order_detail_id'),
            ])
            ->allowedSorts(['id', 'delivery_order_id', 'sales_order_detail_id', 'created_at'])
            ->paginate($this->per_page);

        return DeliveryOrderDetailResource::collection($deliveryOrderDetails);
    }

    public function show(int $deliveryOrderId, int $deliveryOrderDetailId)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('delivery_order_access'), 403);
        $deliveryOrder = DeliveryOrder::findTenanted($deliveryOrderId);
        $deliveryOrderDetail = $deliveryOrder->details()->where('id', $deliveryOrderDetailId)->firstOrFail();

        $deliveryOrderDetail->load([
            'deliveryOrder',
            'salesOrderDetail' => function ($q) {
                $q->with(['warehouse', 'salesOrder', 'productUnit' => fn($q) => $q->withTrashed()]);
            }
        ]);

        return new DeliveryOrderDetailResource($deliveryOrderDetail);
    }

    public function destroy(int $deliveryOrderId, int $deliveryOrderDetailId)
    {
        $deliveryOrder = DeliveryOrder::findTenanted($deliveryOrderId, ['id']);
        abort_if(!auth('sanctum')->user()->tokenCan('delivery_order_delete'), 403);

        $deliveryOrder->details()->where('id', $deliveryOrderDetailId)->delete();

        return $this->deletedResponse();
    }

    /**
     * Reset verified stock for a delivery order detail
     */
    public function resetVerifiedStock(int $deliveryOrderId, int $deliveryOrderDetailId)
    {
        $deliveryOrder = DeliveryOrder::findTenanted($deliveryOrderId);
        if ($deliveryOrder->is_done) {
            throw new BadRequestHttpException("Delivery Order must be not finished. Please set as In Progress first.");
        }

        $deliveryOrderDetail = $deliveryOrder->details()->select('id', 'delivery_order_id', 'sales_order_detail_id')->where('id', $deliveryOrderDetailId)->firstOrFail();

        $salesOrderDetail = $deliveryOrderDetail->salesOrderDetail()->select('id', 'product_unit_id', 'fulfilled_qty')->with('productUnit', fn($q) => $q->select('id', 'name'))->firstOrFail();

        DB::transaction(function () use ($salesOrderDetail) {
            // Revert fulfilled qty in sales order detail
            $salesOrderDetail->update(['fulfilled_qty' => 0]);
            // Delete stock verified
            $salesOrderDetail->salesOrderItems()->orderByDesc('parent_id')->delete();
        });

        return $this->updatedResponse($salesOrderDetail->productUnit->name . " on DO: " . $deliveryOrder->invoice_no . " reset successfully");
    }
}
