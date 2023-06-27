<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryOrderResource;
use App\Http\Requests\Api\DeliveryOrderStoreRequest;
use App\Http\Requests\Api\DeliveryOrderUpdateRequest;
use App\Http\Requests\Api\SalesOrderItemStoreRequest;
use App\Http\Resources\SalesOrderItemResource;
use App\Models\DeliveryOrder;
use App\Models\SalesOrderDetail;
use App\Models\Stock;
use App\Services\SalesOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

class DeliveryOrderController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('delivery_orders_access'), 403);
        $deliveryOrders = QueryBuilder::for(DeliveryOrder::with('user'))
            ->allowedFilters([
                'invoice_no',
                AllowedFilter::callback('reseller_id', function (Builder $query, $value) {
                    $query->whereHas('salesOrder', fn ($q) => $q->where('reseller_id', $value));
                }),
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'warehouse_id', 'created_at'])
            ->allowedIncludes('salesOrder')
            ->paginate();

        return DeliveryOrderResource::collection($deliveryOrders);
    }

    public function show(DeliveryOrder $deliveryOrder)
    {
        abort_if(!auth()->user()->tokenCan('delivery_order_create'), 403);

        $deliveryOrder->load([
            'salesOrder' => function ($q) {
                $q->with('reseller');
                $q->with('details', function ($q) {
                    $q->with('productUnit.product');
                });
            }
        ]);

        return new DeliveryOrderResource($deliveryOrder);
    }

    public function store(DeliveryOrderStoreRequest $request)
    {
        $deliveryOrder = DeliveryOrder::create($request->validated());

        return new DeliveryOrderResource($deliveryOrder);
    }

    public function update(DeliveryOrder $deliveryOrder, DeliveryOrderUpdateRequest $request)
    {
        $deliveryOrder->update($request->validated());

        return (new DeliveryOrderResource($deliveryOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(DeliveryOrder $deliveryOrder)
    {
        abort_if(!auth()->user()->tokenCan('delivery_order_delete'), 403);
        $deliveryOrder->delete();
        return $this->deletedResponse();
    }

    public function print(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load([
            'salesOrder' => function ($q) {
                $q->with('reseller');
                $q->with('details', function ($q) {
                    $q->with('productUnit.product');
                    $q->withCount('salesOrderItems');
                });
            }
        ]);

        $pdf = Pdf::loadView('pdf.deliveryOrders.deliveryOrder', ['deliveryOrder' => $deliveryOrder]);

        return $pdf->download('delivery-order-' . $deliveryOrder->code . '.pdf');
    }

    public function exportXml(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load([
            'salesOrder' => function ($q) {
                $q->with('reseller');
                $q->with('details', function ($q) {
                    $q->with('productUnit.product');
                    $q->withCount('salesOrderItems');
                });
            }
        ]);

        return response(view('xml.deliveryOrders.deliveryOrder')->with(compact('deliveryOrder')), 200, [
            'Content-Type' => 'application/xml', // use your required mime type
            'Content-Disposition' => 'attachment; filename="Delivery Order ' . $deliveryOrder->code . '.xml"',
        ]);
    }

    public function verification(DeliveryOrder $deliveryOrder, SalesOrderDetail $salesOrderDetail, SalesOrderItemStoreRequest $request)
    {
        // 1. cek berdasarkan uom dari SO detail nya
        $stock = Stock::where('id', $request->stock_id)
            ->whereHas('stockProductUnit', fn ($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id)->where('warehouse_id', $salesOrderDetail->salesOrder?->warehouse_id))
            // ->where('product_unit_id', $salesOrderDetail->product_unit_id)
            // ->where('warehouse_id', $salesOrderDetail->salesOrder?->warehouse_id)
            ->first();
        if (!$stock) return response()->json(['message' => 'Stock of product not match'], 400);

        $cek = $salesOrderDetail->salesOrderItems()->where('stock_id', $stock?->id)->exists();

        if ($cek) return response()->json(['message' => 'The product has been scanned'], 400);

        if ($salesOrderDetail->fulfilled_qty >= $salesOrderDetail->qty) return response()->json(['message' => 'Delivery orders have been fulfilled'], 400);

        DB::beginTransaction();
        try {
            $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
                'stock_id' => $stock->id
            ]);

            SalesOrderService::countFulfilledQty($salesOrderDetail);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }

        return new SalesOrderItemResource($salesOrderItem);
    }

    public function done(DeliveryOrder $deliveryOrder, Request $request)
    {
        $request->validate(['is_done' => 'required|boolean']);

        if (!$deliveryOrder->salesOrder?->details->every(fn ($detail) => $detail->fulfilled_qty >= $detail->qty)) return response()->json(['message' => 'All delivery order data must be done'], 400);

        $deliveryOrder->update([
            'is_done' => $request->is_done,
            'done_at' => now(),
        ]);

        $message = 'Data set as ' . ($deliveryOrder->is_done ? 'Done' : 'Pending');
        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
