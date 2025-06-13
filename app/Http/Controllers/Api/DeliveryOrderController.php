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
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DeliveryOrderController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:delivery_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:delivery_order_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:delivery_order_create', ['only' => 'store']);
        $this->middleware('permission:delivery_order_edit', ['only' => 'update']);
        $this->middleware('permission:delivery_order_delete', ['only' => 'destroy']);
        $this->middleware('permission:delivery_order_print', ['only' => 'print']);
        $this->middleware('permission:sales_order_export_xml', ['only' => 'exportXml']);
        $this->middleware('permission:delivery_order_done', ['only' => 'done']);
    }

    public function index()
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('delivery_order_access'), 403);
        $deliveryOrders = QueryBuilder::for(DeliveryOrder::tenanted()->with('user', 'reseller')->withCount('details'))
            ->allowedFilters([
                'invoice_no',
                AllowedFilter::exact('reseller_id'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'is_done', 'created_at'])
            ->paginate($this->per_page);

        return DeliveryOrderResource::collection($deliveryOrders);
    }

    public function show(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('delivery_order_access'), 403);

        $deliveryOrder = DeliveryOrder::findTenanted($id);
        $deliveryOrder->load([
            'user',
            'reseller'
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

    public function update(int $id, DeliveryOrderUpdateRequest $request)
    {
        $deliveryOrder = DeliveryOrder::findTenanted($id);
        $deliveryOrder->update($request->validated());

        return response()->json(['data' => $deliveryOrder], 200);
    }

    public function destroy(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('delivery_order_delete'), 403);
        $deliveryOrder = DeliveryOrder::findTenanted($id);
        DB::beginTransaction();
        try {
            $deliveryOrder->details->each(fn($d) => $d->salesOrderDetail->update(['fulfilled_qty' => 0]));
            $deliveryOrder->delete();
            SalesOrderItem::whereIn('sales_order_detail_id', $deliveryOrder->details->pluck('sales_order_detail_id'))->delete();

            // $deliveryOrder->salesOrder?->details->each(function ($detail) use ($deliveryOrder) {
            //     $stockProductUnit = StockProductUnit::tenanted()->where('warehouse_id', $deliveryOrder->salesOrder?->warehouse_id)
            //         ->where('product_unit_id', $detail->product_unit_id)
            //         ->first(['id']);

            //     if ($stockProductUnit) {
            //         // create history
            //         $detail->histories()->create([
            //             'user_id' => auth('sanctum')->id(),
            //             'stock_product_unit_id' => $stockProductUnit->id,
            //             'value' => $detail->fulfilled_qty,
            //             'is_increment' => 1,
            //             'description' => $deliveryOrder->invoice_no . ' - Delete DO',
            //             'ip' => request()->ip(),
            //             'agent' => request()->header('user-agent'),
            //         ]);
            //     }
            // });

            if ($deliveryOrder->is_done) {
                $deliveryOrder->details?->each(function ($detail) use ($deliveryOrder) {
                    $salesOrderDetail = $detail->salesOrderDetail->load('packaging');

                    $stockProductUnit = StockProductUnit::tenanted()->where('warehouse_id', $salesOrderDetail?->warehouse_id)
                        ->where('product_unit_id', $salesOrderDetail?->product_unit_id)
                        ->first(['id']);

                    if ($stockProductUnit) {
                        // create history
                        if ($salesOrderDetail?->fulfilled_qty > 0) {
                            $history = $stockProductUnit->histories()->create([
                                'user_id' => auth('sanctum')->id(),
                                'stock_product_unit_id' => $stockProductUnit->id,
                                'value' => $salesOrderDetail?->fulfilled_qty ?? 0,
                                'is_increment' => 1,
                                'description' => $deliveryOrder->invoice_no . ' - Delete DO',
                                'ip' => request()->ip(),
                                'agent' => request()->header('user-agent'),
                            ]);

                            if ($salesOrderDetail->packaging) {
                                // record stock history for packaging
                                $stockProductUnit = $salesOrderDetail->packaging->stockProductUnits()->where('warehouse_id', $salesOrderDetail?->warehouse_id)->first();

                                if (!$stockProductUnit->productUnit->is_generate_qr) {
                                    $stockProductUnit->increment('qty', $history->value);
                                }

                                $stockProductUnit->histories()->create([
                                    'user_id' => $history->user_id,
                                    'stock_product_unit_id' => $stockProductUnit->id,
                                    'value' => $history->value,
                                    'is_increment' => $history->is_increment,
                                    'description' => $history->description,
                                    'ip' => $history->ip,
                                    'agent' => $history->agent,
                                ]);
                            }
                        }
                    }
                });
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return $this->deletedResponse();
    }

    public function print(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('delivery_order_print'), 403);

        // $deliveryOrder->load(['reseller', 'details' => fn ($q) => $q->with('salesOrderDetail.productUnit.uom')]);
        $deliveryOrder = DeliveryOrder::with(['reseller', 'details' => fn($q) => $q->with('salesOrderDetail.productUnit.uom')])->findTenanted($id);
        $deliveryOrderDetailsChunk = $deliveryOrder->details?->chunk(23) ?? collect([]);
        $pdf = Pdf::setPaper('a4', 'portrait')->loadView('pdf.deliveryOrders.deliveryOrder', ['deliveryOrder' => $deliveryOrder, 'deliveryOrderDetailsChunk' => $deliveryOrderDetailsChunk]);

        return $pdf->download('delivery-order-' . $deliveryOrder->code . '.pdf');
    }

    public function exportXml(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('sales_order_export_xml'), 403);

        // $deliveryOrder->load(['reseller', 'details' => fn ($q) => $q->with('salesOrderDetail.productUnit.uom')]);

        $deliveryOrder = DeliveryOrder::with(['reseller', 'details' => fn($q) => $q->with('salesOrderDetail.productUnit.uom')])->findTenanted($id);
        return response(view('xml.deliveryOrders.deliveryOrder')->with(compact('deliveryOrder')), 200, [
            'Content-Type' => 'application/xml',
            // use your required mime type
            'Content-Disposition' => 'attachment; filename="Delivery Order ' . $deliveryOrder->code . '.xml"',
        ]);
    }

    public function verification(int $id, DeliveryOrderDetail $deliveryOrderDetail, SalesOrderItemStoreRequest $request)
    {
        $deliveryOrder = DeliveryOrder::findTenanted($id, ['id', 'is_done']);
        if ($deliveryOrder->is_done) return response()->json(['message' => 'Delivery Order sudah diselesaikan. Batalkan untuk dapat scan lagi'], 404);

        // 1. cek if exist $deliveryOrderDetail
        $salesOrderDetail = $deliveryOrderDetail->salesOrderDetail;
        if (!$salesOrderDetail)
            return response()->json(['message' => 'Sales order item Tidak ditemukan'], 404);

        // 2. cek stock_id nya apakah sesuai dengan product unit dan warehouse dari SO detail
        $stock = Stock::where('id', $request->stock_id)
            ->whereHas('stockProductUnit', fn($q) => $q->where('product_unit_id', $salesOrderDetail->product_unit_id)->where('warehouse_id', $salesOrderDetail->salesOrder?->warehouse_id))
            ->first();
        if (!$stock)
            return response()->json(['message' => 'Stok produk tidak sesuai'], 400);

        // 3. cek apakah stock sudah pernah di scan
        // $cek = $salesOrderDetail->salesOrderItems()->where('stock_id', $stock?->id)->exists();
        $cek = SalesOrderItem::where('stock_id', $stock?->id)->exists();
        if ($cek)
            return response()->json(['message' => 'Product sudah di scan'], 400);

        // 4. cek apakah required qty dari SO sudah terpenuhi
        // 5. jika stock_id yang di scan adalah grouping, hitung dulu jumlah childs nya lalu compare dengan required qty yang ada di step 4
        $fulfilledQty = $salesOrderDetail->salesOrderItems()->where('is_parent', 0)->count() ?? 0;
        if ($fulfilledQty >= $salesOrderDetail->qty) {
            return response()->json(['message' => 'Qty sudah terpenuhi'], 400);
        }

        $stock->load(['childs' => fn($q) => $q->select('id', 'parent_id')]);
        $stockChilds = $stock->childs;
        $totalChilds = $stockChilds->count() ?? 0;
        if ($totalChilds > 0) {

            $stockIds = $salesOrderDetail->salesOrderItems->filter(fn($salesOrderItem) => $stockChilds->contains($salesOrderItem->stock_id))?->pluck('stock_id');
            if ($stockIds->count() > 0) {
                // 1. kalo parent yang di scan child nya sudah ada
                $totalStockScanned = $fulfilledQty + $totalChilds - $stockIds->count();
            } else {
                // 2. kalo parent yang di scan child nya ga ada
                $totalStockScanned = $fulfilledQty + $totalChilds;
            }

            if ($totalStockScanned > $salesOrderDetail->qty) {
                return response()->json(['message' => sprintf('Jumlah barang yang di scan (%d) melebihi qty. Tersisa %d lagi', $totalChilds - $stockIds->count(), $salesOrderDetail->qty - $fulfilledQty)], 400);
            }

            $dataStocks = []; // data stock to be insert into salesOrderItems
            // 6. insert stock_id ke sales_order_items. jika stock grouping, insert childs nya
            DB::beginTransaction();
            try {
                $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
                    'stock_id' => $stock->id,
                    'is_parent' => true,
                ]);

                SalesOrderItem::whereIn('stock_id', $stockIds)->delete();
                foreach ($stock->childs as $child) {
                    $dataStocks[] = ['stock_id' => $child->id, 'sales_order_detail_id' => $salesOrderDetail->id];
                }

                $salesOrderItem->childs()->createMany($dataStocks);

                SalesOrderService::countFulfilledQty($salesOrderDetail);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 500);
            }
        } else {
            // 6. insert stock_id ke sales_order_items. jika stock grouping, insert childs nya
            DB::beginTransaction();
            try {
                $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
                    'stock_id' => $stock->id,
                ]);

                SalesOrderService::countFulfilledQty($salesOrderDetail);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }

        return new SalesOrderItemResource($salesOrderItem);
    }

    public function done(int $id, Request $request)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('delivery_order_done'), 403);
        $request->validate(['is_done' => 'required|boolean']);

        $deliveryOrder = DeliveryOrder::findTenanted($id, ['id', 'is_done', 'invoice_no']);

        // matiin dulu disuruh bu kur
        // if (!$deliveryOrder->details->every(fn ($detail) => $detail->salesOrderDetail?->salesOrderItems?->count() >= $detail->salesOrderDetail?->qty))
        //     return response()->json(['message' => 'Semua data delivery order harus terpenuhi'], 400);

        DB::beginTransaction();
        try {
            $deliveryOrder->update([
                'is_done' => $request->is_done,
                'done_at' => now(),
            ]);

            if ($deliveryOrder->is_done) {
                $deliveryOrder->details?->each(function ($detail) use ($deliveryOrder) {
                    $salesOrderDetail = $detail->salesOrderDetail->load('packaging');

                    $stockProductUnit = StockProductUnit::tenanted()->where('warehouse_id', $salesOrderDetail?->warehouse_id)
                        ->where('product_unit_id', $salesOrderDetail?->product_unit_id)
                        ->first(['id']);

                    if ($stockProductUnit) {
                        // create history
                        $history = $salesOrderDetail->histories()->create([
                            'user_id' => auth('sanctum')->id(),
                            'stock_product_unit_id' => $stockProductUnit->id,
                            'value' => $salesOrderDetail?->fulfilled_qty ?? 0,
                            'is_increment' => 0,
                            'description' => $deliveryOrder->invoice_no . ' - Verified DO',
                            'ip' => request()->ip(),
                            'agent' => request()->header('user-agent'),
                        ]);

                        if ($salesOrderDetail->packaging) {
                            // record stock history for packaging
                            $stockProductUnit = $salesOrderDetail->packaging->stockProductUnits()->where('warehouse_id', $salesOrderDetail?->warehouse_id)->first();

                            if (!$stockProductUnit->productUnit->is_generate_qr) {
                                $stockProductUnit->decrement('qty', $history->value);
                            }

                            $salesOrderDetail->histories()->create([
                                'user_id' => $history->user_id,
                                'stock_product_unit_id' => $stockProductUnit->id,
                                'value' => $history->value,
                                'is_increment' => $history->is_increment,
                                'description' => $history->description,
                                'ip' => $history->ip,
                                'agent' => $history->agent,
                            ]);
                        }
                    }
                });
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }

        $message = 'Data set as ' . ($deliveryOrder->is_done ? 'Done' : 'Pending');
        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function attach(int $id, DeliveryOrderAttachRequest $request)
    {
        $count = 0;
        $deliveryOrder = DeliveryOrder::findTenanted($id, ['id']);
        foreach ($request->sales_order_detail_ids ?? [] as $id) {
            if ($deliveryOrder->details()->where('sales_order_detail_id', $id)->doesntExist()) {
                $deliveryOrder->details()->create([
                    'sales_order_detail_id' => $id
                ]);
                $count++;
            }
        }

        return response()->json(['message' => $count . ' Sales order berahsil ditambahkan ke delivery order']);
    }
}
