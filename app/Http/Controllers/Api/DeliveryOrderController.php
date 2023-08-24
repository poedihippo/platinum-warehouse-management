<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeliveryOrderAttachRequest;
use App\Http\Resources\DeliveryOrderResource;
use App\Http\Requests\Api\DeliveryOrderStoreRequest;
use App\Http\Requests\Api\DeliveryOrderUpdateRequest;
use App\Http\Requests\Api\SalesOrderItemStoreRequest;
use App\Http\Resources\SalesOrderItemResource;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use App\Models\StockProductUnit;
use App\Services\SalesOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class DeliveryOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:delivery_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:delivery_order_create', ['only' => 'store']);
        $this->middleware('permission:delivery_order_edit', ['only' => 'update']);
        $this->middleware('permission:delivery_order_delete', ['only' => 'destroy']);
        $this->middleware('permission:delivery_order_print', ['only' => 'print']);
        $this->middleware('permission:sales_order_export_xml', ['only' => 'exportXml']);
        $this->middleware('permission:delivery_order_done', ['only' => 'done']);
    }

    public function index()
    {
        // abort_if(!auth()->user()->tokenCan('delivery_order_access'), 403);
        $deliveryOrders = QueryBuilder::for(DeliveryOrder::with('user', 'reseller')->withCount('details'))
            ->allowedFilters([
                'invoice_no',
                'reseller_id',
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'is_done', 'created_at'])
            ->paginate();

        return DeliveryOrderResource::collection($deliveryOrders);
    }

    public function show(DeliveryOrder $deliveryOrder)
    {
        // abort_if(!auth()->user()->tokenCan('delivery_order_access'), 403);

        $deliveryOrder->load([
            'user', 'reseller'
            // 'salesOrder' => function ($q) {
            //     $q->with('reseller');
            //     $q->with('details', function ($q) {
            //         $q->with('productUnit.product');
            //     });
            // }
        ]);

        return new DeliveryOrderResource($deliveryOrder);
    }

    public function store(DeliveryOrderStoreRequest $request)
    {
        $deliveryOrder = DeliveryOrder::create($request->validated());

        return response()->json(['data' => $deliveryOrder], 201);
    }

    public function update(DeliveryOrder $deliveryOrder, DeliveryOrderUpdateRequest $request)
    {
        $deliveryOrder->update($request->validated());

        return response()->json(['data' => $deliveryOrder], 200);
    }

    public function destroy(DeliveryOrder $deliveryOrder)
    {
        // abort_if(!auth()->user()->tokenCan('delivery_order_delete'), 403);

        DB::beginTransaction();
        try {
            $deliveryOrder->delete();

            // $deliveryOrder->salesOrder?->details->each(function ($detail) use ($deliveryOrder) {
            //     $stockProductUnit = StockProductUnit::select('id')->where('warehouse_id', $deliveryOrder->salesOrder?->warehouse_id)
            //         ->where('product_unit_id', $detail->product_unit_id)
            //         ->first();

            //     if ($stockProductUnit) {
            //         // create history
            //         $detail->histories()->create([
            //             'user_id' => auth()->user()->id,
            //             'stock_product_unit_id' => $stockProductUnit->id,
            //             'value' => $detail->fulfilled_qty,
            //             'is_increment' => 1,
            //             'description' => $deliveryOrder->invoice_no . ' - Delete DO',
            //             'ip' => request()->ip(),
            //             'agent' => request()->header('user-agent'),
            //         ]);
            //     }
            // });

            $deliveryOrder->details?->each(function ($detail) use ($deliveryOrder) {
                $salesOrderDetail = $detail->salesOrderDetail;
                $stockProductUnit = StockProductUnit::select('id')->where('warehouse_id', $salesOrderDetail?->warehouse_id)
                    ->where('product_unit_id', $salesOrderDetail?->product_unit_id)
                    ->first();

                if ($stockProductUnit) {
                    // create history
                    $detail->histories()->create([
                        'user_id' => auth()->user()->id,
                        'stock_product_unit_id' => $stockProductUnit->id,
                        'value' => $salesOrderDetail?->fulfilled_qty ?? 0,
                        'is_increment' => 1,
                        'description' => $deliveryOrder->invoice_no . ' - Delete DO',
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);
                }
            });
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }

        return $this->deletedResponse();
    }

    public function print(DeliveryOrder $deliveryOrder)
    {
        // abort_if(!auth()->user()->tokenCan('delivery_order_print'), 403);

        $deliveryOrder->load(['reseller', 'details' => fn ($q) => $q->with('salesOrderDetail.productUnit.uom')]);
        $pdf = Pdf::setPaper('a4', 'portrait')->loadView('pdf.deliveryOrders.deliveryOrder', ['deliveryOrder' => $deliveryOrder]);

        return $pdf->download('delivery-order-' . $deliveryOrder->code . '.pdf');
    }

    public function exportXml(DeliveryOrder $deliveryOrder)
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_export_xml'), 403);

        $deliveryOrder->load(['reseller', 'details' => fn ($q) => $q->with('salesOrderDetail.productUnit.uom')]);

        return response(view('xml.deliveryOrders.deliveryOrder')->with(compact('deliveryOrder')), 200, [
            'Content-Type' => 'application/xml', // use your required mime type
            'Content-Disposition' => 'attachment; filename="Delivery Order ' . $deliveryOrder->code . '.xml"',
        ]);
    }

    // public function verification(DeliveryOrder $deliveryOrder, SalesOrderDetail $salesOrderDetail, SalesOrderItemStoreRequest $request)
    // {
    //     // 1. cek berdasarkan uom dari SO detail nya
    //     $stock = Stock::where('id', $request->stock_id)
    //         ->whereHas('stockProductUnit', fn ($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id)->where('warehouse_id', $salesOrderDetail->salesOrder?->warehouse_id))
    //         // ->where('product_unit_id', $salesOrderDetail->product_unit_id)
    //         // ->where('warehouse_id', $salesOrderDetail->salesOrder?->warehouse_id)
    //         ->first();
    //     if (!$stock) return response()->json(['message' => 'Stock of product not match'], 400);

    //     $cek = $salesOrderDetail->salesOrderItems()->where('stock_id', $stock?->id)->exists();

    //     if ($cek) return response()->json(['message' => 'The product has been scanned'], 400);

    //     if ($salesOrderDetail->fulfilled_qty >= $salesOrderDetail->qty) return response()->json(['message' => 'Delivery orders have been fulfilled'], 400);

    //     DB::beginTransaction();
    //     try {
    //         $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
    //             'stock_id' => $stock->id
    //         ]);

    //         SalesOrderService::countFulfilledQty($salesOrderDetail);
    //         DB::commit();
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         return response()->json(['message' => $th->getMessage()], 500);
    //     }

    //     return new SalesOrderItemResource($salesOrderItem);
    // }

    public function verification(DeliveryOrder $deliveryOrder, DeliveryOrderDetail $deliveryOrderDetail, SalesOrderItemStoreRequest $request)
    {
        $salesOrderDetail = $deliveryOrderDetail->salesOrderDetail;
        if (!$salesOrderDetail) return response()->json(['message' => 'Sales order item not found'], 404);

        // 1. cek berdasarkan uom dari SO detail nya
        $stock = Stock::where('id', $request->stock_id)
            ->whereHas('stockProductUnit', fn ($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id)->where('warehouse_id', $salesOrderDetail->salesOrder?->warehouse_id))
            // ->where('product_unit_id', $salesOrderDetail->product_unit_id)
            // ->where('warehouse_id', $salesOrderDetail->salesOrder?->warehouse_id)
            ->first();
        if (!$stock) return response()->json(['message' => 'Stock of product not match'], 400);

        // $cek = $salesOrderDetail->salesOrderItems()->where('stock_id', $stock?->id)->exists();
        $cek = SalesOrderItem::where('stock_id', $stock?->id)->exists();

        if ($cek) return response()->json(['message' => 'The product has been scanned'], 400);

        if ($salesOrderDetail->salesOrderItems->count() >= $salesOrderDetail->qty) return response()->json(['message' => 'Delivery orders have been fulfilled'], 400);

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
        // abort_if(!auth()->user()->tokenCan('delivery_order_done'), 403);
        $request->validate(['is_done' => 'required|boolean']);

        // if (!$deliveryOrder->salesOrder?->details->every(fn ($detail) => $detail->fulfilled_qty >= $detail->qty)) return response()->json(['message' => 'All delivery order data must be done'], 400);
        if (!$deliveryOrder->details->every(fn ($detail) => $detail->salesOrderDetail?->salesOrderItems?->count() >= $detail->salesOrderDetail?->qty)) return response()->json(['message' => 'All delivery order data must be fulfilled'], 400);

        DB::beginTransaction();
        try {
            $deliveryOrder->update([
                'is_done' => $request->is_done,
                'done_at' => now(),
            ]);

            // $deliveryOrder->salesOrder?->details->each(function ($detail) use ($deliveryOrder) {
            //     $stockProductUnit = StockProductUnit::select('id')->where('warehouse_id', $deliveryOrder->salesOrder?->warehouse_id)
            //         ->where('product_unit_id', $detail->product_unit_id)
            //         ->first();

            //     if ($stockProductUnit) {
            //         // create history
            //         $detail->histories()->create([
            //             'user_id' => auth()->user()->id,
            //             'stock_product_unit_id' => $stockProductUnit->id,
            //             'value' => $detail->fulfilled_qty,
            //             'is_increment' => 0,
            //             'description' => $deliveryOrder->invoice_no . ' - Verified DO',
            //             'ip' => request()->ip(),
            //             'agent' => request()->header('user-agent'),
            //         ]);
            //     }
            // });

            $deliveryOrder->details?->each(function ($detail) use ($deliveryOrder) {
                $salesOrderDetail = $detail->salesOrderDetail;

                $stockProductUnit = StockProductUnit::select('id')->where('warehouse_id', $salesOrderDetail?->warehouse_id)
                    ->where('product_unit_id', $salesOrderDetail?->product_unit_id)
                    ->first();

                if ($stockProductUnit) {
                    // create history
                    $salesOrderDetail->histories()->create([
                        'user_id' => auth()->user()->id,
                        'stock_product_unit_id' => $stockProductUnit->id,
                        'value' => $salesOrderDetail?->fulfilled_qty ?? 0,
                        'is_increment' => 0,
                        'description' => $deliveryOrder->invoice_no . ' - Verified DO',
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);
                }
            });
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }

        $message = 'Data set as ' . ($deliveryOrder->is_done ? 'Done' : 'Pending');
        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function attach(DeliveryOrder $deliveryOrder, DeliveryOrderAttachRequest $request)
    {
        $count = 0;
        foreach ($request->sales_order_detail_ids ?? [] as $id) {
            if ($deliveryOrder->details()->where('sales_order_detail_id', $id)->doesntExist()) {
                $deliveryOrder->details()->create([
                    'sales_order_detail_id' => $id
                ]);
                $count++;
            }
        }

        return response()->json(['message' => $count . ' sales order success added to delivery order']);
    }
}
