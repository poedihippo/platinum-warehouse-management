<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SalesOrderItemStoreRequest;
use App\Http\Requests\Api\SalesOrderMergeRequest;
use App\Http\Requests\Api\SalesOrderStoreRequest;
use App\Http\Requests\Api\SalesOrderUpdateRequest;
use App\Http\Resources\SalesOrderItemResource;
use App\Http\Resources\SalesOrderResource;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use App\Models\StockProductUnit;
use App\Services\SalesOrderService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SalesOrderController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:sales_order_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:sales_order_read', ['only' => ['index', 'show']]);
        // $this->middleware('permission:sales_order_create', ['only' => 'store']);
        // $this->middleware('permission:sales_order_edit', ['only' => 'update']);
        // $this->middleware('permission:sales_order_delete', ['only' => 'destroy']);
        // $this->middleware('permission:sales_order_print', ['only' => 'print']);
        // $this->middleware('permission:sales_order_export_xml', ['only' => 'exportXml']);
    }

    public function index()
    {
        return SalesOrderService::index($this->per_page);
    }

    public function show($id)
    {
        $salesOrder = SalesOrderService::show($id);

        return new SalesOrderResource($salesOrder);
    }

    public function items(int $id, SalesOrderDetail $salesOrderDetail)
    {
        $salesOrder = $this->findScanableMarketplaceOrder($id);
        abort_unless($salesOrderDetail->sales_order_id === $salesOrder->id, 404);

        $items = QueryBuilder::for(
            SalesOrderItem::query()
                ->where('sales_order_detail_id', $salesOrderDetail->id)
                ->select(SalesOrderItem::SELECT_COLUMNS)
                ->with('stock')
        )
            ->allowedFilters([
                'is_returned',
                'is_parent',
            ])
            ->paginate($this->per_page);

        return SalesOrderItemResource::collection($items);
    }

    public function verification(SalesOrderItemStoreRequest $request, int $id, SalesOrderDetail $salesOrderDetail)
    {
        $salesOrder = $this->findScanableMarketplaceOrder($id);
        abort_unless($salesOrderDetail->sales_order_id === $salesOrder->id, 404);

        $salesOrderItem = SalesOrderService::verifyStockItem($salesOrderDetail, $request->stock_id);

        return new SalesOrderItemResource($salesOrderItem);
    }

    public function store(SalesOrderStoreRequest $request)
    {
        $salesOrder = SalesOrderService::createOrder(SalesOrder::make(['raw_source' => $request->validated()]), (bool) $request->is_preview ?? false);

        return new SalesOrderResource($this->loadMarketplaceDeliveryOrder($salesOrder));
    }

    public function merge(SalesOrderMergeRequest $request)
    {
        $salesOrder = SalesOrderService::mergeMarketplaceOrders($request->validated());
        $salesOrder->load('mergedSalesOrders:id,merged_into_sales_order_id,invoice_no');

        return new SalesOrderResource($this->loadMarketplaceDeliveryOrder($salesOrder));
    }

    public function update($id, SalesOrderUpdateRequest $request)
    {
        $salesOrder = SalesOrder::findTenanted($id);

        if ($salesOrder->isMergeSalesOrder()) {
            return response()->json(['message' => 'Sales Order sumber merge tidak dapat diubah.'], 400);
        }

        if ($salesOrder->isMergeResult()) {
            return response()->json(['message' => 'Sales Order hasil merge tidak dapat diubah. Hapus Sales Order hasil merge untuk membatalkan merge.'], 400);
        }

        if (
            ! $salesOrder->marketplace
            && SalesOrderDetail::where('sales_order_id', $salesOrder->id)->whereHas('deliveryOrderDetails')->exists()
        ) {
            return response()->json(['message' => 'DO harus dihapus terlebih dahulu sebelum mengedit SO'], 400);
        }

        $salesOrder->raw_source = $request->validated();
        $salesOrder = SalesOrderService::updateOrder($salesOrder, (bool) $request->is_preview ?? false);

        return (new SalesOrderResource($this->loadMarketplaceDeliveryOrder($salesOrder)))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy($id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('sales_order_delete'), 403);
        $salesOrder = SalesOrder::findTenanted($id);

        if ($salesOrder->isMergeSalesOrder()) {
            return response()->json(['message' => 'Sales Order sumber merge tidak dapat dihapus.'], 400);
        }

        if ($salesOrder->isMergeResult()) {
            SalesOrderService::deleteMergeResult($salesOrder);

            return $this->deletedResponse();
        }

        if ($salesOrder->has_delivery_order) {
            return response()->json(['message' => 'Tidak dapat menghapus SO yang sudah direlasikan dengan DO'], 400);
        }

        DB::transaction(function () use ($salesOrder) {
            if (! $salesOrder->deliveryOrders()->exists()) {
                SalesOrderService::releaseScannedStock($salesOrder);
            }

            $salesOrder->delete();
        });

        return $this->deletedResponse();
    }

    public function print($id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('sales_order_print'), 403);
        return SalesOrderService::print($id);
    }

    public function exportXml($id)
    {
        return SalesOrderService::exportXml($id);
    }

    public function productUnitsNew()
    {
        // $userDiscounts = UserDiscount::select('product_brand_id', 'value', 'is_percentage')->where('user_id', $request->customer_id)->get();

        // Sebelumnya: request()->filter['warehouse_id'] ?? null;
        // Diganti ke input() supaya tidak memicu warning "Trying to access array offset on null"
        // saat request datang tanpa parameter filter[].
        $warehouseId = request()->input('filter.warehouse_id');

        /*
         * CATATAN PERFORMA (2026-10-23)
         *
         * withCount('stocks') di bawah dihitung sebagai NOT EXISTS per baris stocks
         * (376rb stocks x 324rb sales_order_items). Untuk product unit besar
         * (contoh stock_product_unit 967 = 94rb stock) satu produk saja butuh ~1,9 detik,
         * sementara frontend tidak memakai stocks_count (max qty di
         * components/molecules/counter/index.tsx masih di-comment).
         *
         * Untuk mengaktifkan kembali: uncomment baris withCount() di bawah.
         */
        $stockProductUnitQuery = fn ($q) => $q->select('id', 'warehouse_id', 'product_unit_id')->has('productUnit')->has('warehouse')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            // ->withCount(['stocks' => fn ($q) => $q->whereAvailableStock()->whereIsStock()->whereNull('description')])
            ->with([
                'warehouse' => fn ($q) => $q->select('id', 'code'),
            ]);

        // ProductUnit::select('id', 'refer_id', 'uom_id', 'product_id', 'name', 'price', 'is_ppn', 'code')
        // product_id tidak lagi di-select karena relasi 'product' (satu-satunya pemakainya) tidak dipakai frontend.
        $query = ProductUnit::select('id', 'refer_id', 'uom_id', 'name', 'price', 'is_ppn', 'code')
            ->where('is_generate_qr', true)
            ->with([
                'refer' => fn ($q) => $q->select('id', 'name')->with('stockProductUnit', $stockProductUnitQuery),
                'uom' => fn ($q) => $q->select('id', 'name'),
                // 'product' => fn ($q) => $q->select('id', 'name', 'product_brand_id'),
            ])
            ->with('stockProductUnit', $stockProductUnitQuery)
            // Order deterministik supaya pagination tidak menghasilkan baris dobel/hilang.
            ->orderBy('id');

        $productUnits = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::callback('warehouse_id', fn ($q) => $q),
                AllowedFilter::scope('product_unit', 'search'),
                AllowedFilter::scope('company', 'whereCompany'),
            ])
            ->paginate($this->per_page)
            ->through(function (ProductUnit $productUnit) {
                $productUnit->price_discount = $productUnit->price;
                $productUnit->discount = 0;
                $productUnit->is_percentage = 1;

                if ($productUnit->refer && ! $productUnit->stockProductUnit) {
                    // Sebelumnya: ($productUnit->refer->stockProductUnit->warehouse?->code ?? '')
                    $productUnit->name = $productUnit->name.' - '.($productUnit->refer->stockProductUnit?->warehouse?->code ?? '');
                    $productUnit->setRelation('stockProductUnit', $productUnit->refer->stockProductUnit);
                } else {
                    // Sebelumnya: ($productUnit->stockProductUnit->warehouse?->code ?? '')
                    $productUnit->name = $productUnit->name.' - '.($productUnit->stockProductUnit?->warehouse?->code ?? '');
                }
                $productUnit->unsetRelation('refer');

                return $productUnit;
            });

        return response()->json($productUnits);
    }

    public function productUnits()
    {
        // $userDiscounts = UserDiscount::select('product_brand_id', 'value', 'is_percentage')->where('user_id', $request->customer_id)->get();

        $query = StockProductUnit::select('id', 'warehouse_id', 'product_unit_id')->has('productUnit')->has('warehouse')
            ->withCount(['stocks' => fn ($q) => $q->whereAvailableStock()->whereNull('description')])
            ->with([
                'warehouse' => fn ($q) => $q->select('id', 'code'),
                'productUnit' => function ($q) {
                    // $q->select('id', 'uom_id', 'product_id', 'name', 'price', 'is_ppn')
                    $q->select('id', 'uom_id', 'product_id', 'name', 'price', 'is_ppn')
                        ->with([
                            'uom' => fn ($q) => $q->select('id', 'name'),
                            'product' => fn ($q) => $q->select('id', 'name', 'product_brand_id'),
                        ]);
                },
            ]);
        // ->having('stocks_count', '>', 0);

        $stockProductUnits = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::scope('product_unit'),
                AllowedFilter::scope('company', 'whereCompany'),
            ])
            ->paginate($this->per_page)
            ->through(function ($stockProductUnit) {
                $productUnit = $stockProductUnit->productUnit;
                $productUnit->name = $productUnit->name.' - '.($stockProductUnit->warehouse?->code ?? '');
                $productUnit->price_discount = $productUnit->price;
                $productUnit->discount = 0;
                $productUnit->is_percentage = 1;

                return $stockProductUnit;
            });

        return response()->json($stockProductUnits);
    }

    private function loadMarketplaceDeliveryOrder(SalesOrder $salesOrder): SalesOrder
    {
        if ($salesOrder->marketplace || $salesOrder->isMergeResult()) {
            $salesOrder->load('deliveryOrders:id,sales_order_id')->makeHidden('deliveryOrders');
        }

        return $salesOrder;
    }

    /**
     * Resolve a marketplace Sales Order that is eligible for stock scanning.
     *
     * Merge results and merge sources are rejected, as are orders that already have a Delivery Order.
     */
    private function findScanableMarketplaceOrder(int $id): SalesOrder
    {
        $salesOrder = SalesOrder::whereNotNull('marketplace')->findTenanted($id);

        if ($salesOrder->isMergeResult() || $salesOrder->isMergeSalesOrder()) {
            abort(Response::HTTP_BAD_REQUEST, 'Sales Order merge tidak dapat diverifikasi.');
        }

        if ($salesOrder->deliveryOrders()->exists()) {
            abort(Response::HTTP_BAD_REQUEST, 'Sales Order sudah memiliki Delivery Order dan tidak dapat diverifikasi.');
        }

        return $salesOrder;
    }
}
