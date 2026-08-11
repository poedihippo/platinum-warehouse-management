<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeliveryOrderDetailUpdateRequest;
use App\Http\Resources\DeliveryOrderDetailResource;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\SalesOrderItem;
use App\Services\SalesOrderService;
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
        $deliveryOrderDetails = QueryBuilder::for(
            DeliveryOrderDetail::select('id', 'delivery_order_id', 'qty', 'is_done')
                ->with([
                    'salesOrderItems' => fn($q) => $q->select(SalesOrderItem::SELECT_COLUMNS),
                    'salesOrderDetail' => fn($q) => $q->select('id', 'fulfilled_qty', 'total_price')
                        ->with([
                            // 'warehouse',
                            'salesOrder' => fn($q) => $q->withTrashed()
                                ->select('id', 'invoice_no', 'resseler_id')
                                ->with('reseller', fn($q) => $q->select('id', 'name', 'phone', 'address')),
                            'productUnit' => fn($q) => $q->withTrashed()
                                ->select('id', 'name', 'product_id', 'uom_id')
                                ->with([
                                    'uom' => fn($q) => $q->select('id', 'name'),
                                    'product' => fn($q) => $q->select('id', 'product_category_id', 'product_brand_id')->with([
                                        'productCategory' => fn($q) => $q->select('id', 'name'),
                                        'productBrand' => fn($q) => $q->select('id', 'name')
                                    ])
                                ]),
                        ])
                ])->where('delivery_order_id', $deliveryOrder->id)
        )
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
            'salesOrderItems' => fn($q) => $q->select(SalesOrderItem::SELECT_COLUMNS),
            'salesOrderDetail' => function ($q) {
                $q->with(['warehouse', 'salesOrder', 'productUnit' => fn($q) => $q->withTrashed()]);
            }
        ]);

        return new DeliveryOrderDetailResource($deliveryOrderDetail);
    }

    public function update(int $deliveryOrderId, int $deliveryOrderDetailId, DeliveryOrderDetailUpdateRequest $request)
    {
        $deliveryOrder = DeliveryOrder::findTenanted($deliveryOrderId, ['id', 'is_done', 'invoice_no']);

        if ($deliveryOrder->is_done) {
            throw new BadRequestHttpException("Delivery Order must be not finished. Please set as In Progress first.");
        }

        $deliveryOrderDetail = $deliveryOrder->details()->where('id', $deliveryOrderDetailId)->firstOrFail();

        $deliveryOrderDetail->update([
            'qty' => $request->qty,
        ]);

        $deliveryOrderDetail->load([
            'deliveryOrder',
            'salesOrderItems' => fn($q) => $q->select(SalesOrderItem::SELECT_COLUMNS),
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

        if ($deliveryOrder->is_done) {
            throw new BadRequestHttpException("Delivery Order must be not finished. Please set as In Progress first.");
        }

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

        DB::transaction(function () use ($salesOrderDetail, $deliveryOrderDetail) {
            // Delete stock verified
            $salesOrderDetail->salesOrderItems()
                ->where('delivery_order_detail_id', $deliveryOrderDetail->id)
                ->whereNotReturned()
                ->orderByDesc('parent_id')
                ->delete();
            // Recalculate fulfilled qty in sales order detail
            SalesOrderService::countFulfilledQty($salesOrderDetail);
        });

        return $this->updatedResponse($salesOrderDetail->productUnit->name . " on DO: " . $deliveryOrder->invoice_no . " reset successfully");
    }
}
