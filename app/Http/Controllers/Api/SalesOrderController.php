<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Http\Requests\Api\SalesOrderStoreRequest;
use App\Http\Requests\Api\SalesOrderUpdateRequest;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\StockProductUnit;
use App\Services\SalesOrderService;
use Illuminate\Http\Response;
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

    public function show(int $id)
    {
        $salesOrder = SalesOrderService::show($id);
        return new SalesOrderResource($salesOrder);
    }

    public function store(SalesOrderStoreRequest $request)
    {
        $salesOrder = SalesOrderService::createOrder(SalesOrder::make(['raw_source' => $request->validated()]), (bool) $request->is_preview ?? false);
        return new SalesOrderResource($salesOrder);
    }

    public function update(int $id, SalesOrderUpdateRequest $request)
    {
        $salesOrder = SalesOrder::findTenanted($id);
        if (!$salesOrder->details?->every(fn($salesOrderDetail) => !$salesOrderDetail->deliveryOrderDetail))
            return response()->json(['message' => "DO harus dihapus terlebih dahulu sebelum mengedit SO"], 400);

        $salesOrder->raw_source = $request->validated();
        $salesOrder = SalesOrderService::updateOrder($salesOrder, (bool) $request->is_preview ?? false);
        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('sales_order_delete'), 403);
        $salesOrder = SalesOrder::findTenanted($id);
        if ($salesOrder->has_delivery_order) return response()->json(['message' => "Tidak dapat menghapus SO yang sudah direlasikan dengan DO"], 400);

        $salesOrder->delete();
        return $this->deletedResponse();
    }

    public function print(int $id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('sales_order_print'), 403);
        return SalesOrderService::print($id);
    }

    public function exportXml(int $id)
    {
        return SalesOrderService::exportXml($id);
    }

    public function productUnitsNew()
    {
        // $userDiscounts = UserDiscount::select('product_brand_id', 'value', 'is_percentage')->where('user_id', $request->customer_id)->get();

        // dd(request()->all());
        $warehouseId = request()->filter['warehouse_id'] ?? null;

        $fn = fn($q) => $q->select('id', 'warehouse_id', 'product_unit_id')->has('productUnit')->has('warehouse')
            ->when($warehouseId, fn($q) =>  $q->where('warehouse_id', $warehouseId))
            ->withCount(['stocks' => fn($q) => $q->whereAvailableStock()->whereNull('description')])
            ->with([
                'warehouse' => fn($q) => $q->select('id', 'code'),
            ]);

        $query = ProductUnit::select('id', 'refer_id', 'uom_id', 'product_id', 'name', 'price', 'is_ppn')
            ->with([
                'refer' => fn($q) => $q->select('id', 'name')->with('stockProductUnit', $fn),
                'uom' => fn($q) => $q->select('id', 'name'),
                'product' => fn($q) => $q->select('id', 'name', 'product_brand_id'),
            ])
            ->with('stockProductUnit', $fn);

        $stockProductUnits = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::callback('warehouse_id', fn($q) => $q),
                AllowedFilter::scope('product_unit', 'search'),
                AllowedFilter::scope('company', 'whereCompany'),
            ])
            ->paginate($this->per_page)
            ->through(function ($productUnit) {
                $productUnit->price_discount = $productUnit->price;
                $productUnit->discount = 0;
                $productUnit->is_percentage = 1;

                if ($productUnit->refer && !$productUnit->stockProductUnit) {
                    $productUnit->name = $productUnit->name . ' - ' . ($productUnit->refer->stockProductUnit->warehouse?->code ?? '');
                    $productUnit->setRelation('stockProductUnit', $productUnit->refer->stockProductUnit);
                } else {
                    $productUnit->name = $productUnit->name . ' - ' . ($productUnit->stockProductUnit->warehouse?->code ?? '');
                }
                $productUnit->unsetRelation('refer');

                return $productUnit;
            });
        return response()->json($stockProductUnits);

        // $query = StockProductUnit::select('id', 'warehouse_id', 'product_unit_id')->has('productUnit')->has('warehouse')
        //     ->withCount(['stocks' => fn($q) => $q->whereAvailableStock()->whereNull('description')])
        //     ->with([
        //         'warehouse' => fn($q) => $q->select('id', 'code'),
        //         'productUnit' => function ($q) {
        //             // $q->select('id', 'uom_id', 'product_id', 'packaging_id', 'name', 'price', 'is_ppn')
        //             $q->select('id', 'uom_id', 'product_id', 'name', 'price', 'is_ppn')
        //                 ->with([
        //                     'uom' => fn($q) => $q->select('id', 'name'),
        //                     'product' => fn($q) => $q->select('id', 'name', 'product_brand_id'),
        //                 ]);
        //         },
        //     ]);
        // // ->having('stocks_count', '>', 0);

        // $stockProductUnits = QueryBuilder::for($query)
        //     ->allowedFilters([
        //         AllowedFilter::exact('warehouse_id'),
        //         AllowedFilter::scope('product_unit'),
        //         AllowedFilter::scope('company', 'whereCompany'),
        //     ])
        //     ->paginate($this->per_page)
        //     ->through(function ($stockProductUnit) {
        //         $productUnit = $stockProductUnit->productUnit;
        //         $productUnit->name = $productUnit->name . ' - ' . ($stockProductUnit->warehouse?->code ?? '');
        //         $productUnit->price_discount = $productUnit->price;
        //         $productUnit->discount = 0;
        //         $productUnit->is_percentage = 1;

        //         return $stockProductUnit;
        //     });

        // $stockProductUnits->each(function ($stockProductUnit) {
        //     $productUnit = $stockProductUnit->productUnit;
        //     $productUnit->name = $productUnit->name . ' - ' . $stockProductUnit->warehouse?->code ?? '';
        //     $productUnit->price_discount = $productUnit->price;
        //     $productUnit->discount = 0;
        //     $productUnit->is_percentage = 1;

        //     // $productBrandId = $productUnit?->product?->product_brand_id ?? null;
        //     // if ($userDiscounts->contains('product_brand_id', $productBrandId)) {
        //     //     $discount = $userDiscounts->firstWhere('product_brand_id', $productBrandId);
        //     //     if ($discount->is_percentage) {
        //     //         $totalDiscount = $productUnit->price * $discount->value;
        //     //         $totalDiscount = $totalDiscount <= 0 ? 0 : ($totalDiscount / 100);
        //     //         $totalPrice = $productUnit->price - $totalDiscount;
        //     //     } else {
        //     //         $totalPrice = $productUnit->price - $discount->value;
        //     //     }

        //     //     $productUnit->price_discount = $totalPrice <= 0 ? 0 : $totalPrice;
        //     //     $productUnit->discount = $discount->value;
        //     //     $productUnit->is_percentage = $discount->is_percentage;
        //     // }

        //     unset($stockProductUnit->warehouse_id);
        //     unset($stockProductUnit->product_unit_id);

        //     unset($productUnit->product);
        //     unset($productUnit->uom_id);
        //     unset($productUnit->product_id);
        // });
    }

    public function productUnits()
    {
        // $userDiscounts = UserDiscount::select('product_brand_id', 'value', 'is_percentage')->where('user_id', $request->customer_id)->get();

        $query = StockProductUnit::select('id', 'warehouse_id', 'product_unit_id')->has('productUnit')->has('warehouse')
            ->withCount(['stocks' => fn($q) => $q->whereAvailableStock()->whereNull('description')])
            ->with([
                'warehouse' => fn($q) => $q->select('id', 'code'),
                'productUnit' => function ($q) {
                    // $q->select('id', 'uom_id', 'product_id', 'packaging_id', 'name', 'price', 'is_ppn')
                    $q->select('id', 'uom_id', 'product_id', 'name', 'price', 'is_ppn')
                        ->with([
                            'uom' => fn($q) => $q->select('id', 'name'),
                            'product' => fn($q) => $q->select('id', 'name', 'product_brand_id'),
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
                $productUnit->name = $productUnit->name . ' - ' . ($stockProductUnit->warehouse?->code ?? '');
                $productUnit->price_discount = $productUnit->price;
                $productUnit->discount = 0;
                $productUnit->is_percentage = 1;

                return $stockProductUnit;
            });

        // $stockProductUnits->each(function ($stockProductUnit) {
        //     $productUnit = $stockProductUnit->productUnit;
        //     $productUnit->name = $productUnit->name . ' - ' . $stockProductUnit->warehouse?->code ?? '';
        //     $productUnit->price_discount = $productUnit->price;
        //     $productUnit->discount = 0;
        //     $productUnit->is_percentage = 1;

        //     // $productBrandId = $productUnit?->product?->product_brand_id ?? null;
        //     // if ($userDiscounts->contains('product_brand_id', $productBrandId)) {
        //     //     $discount = $userDiscounts->firstWhere('product_brand_id', $productBrandId);
        //     //     if ($discount->is_percentage) {
        //     //         $totalDiscount = $productUnit->price * $discount->value;
        //     //         $totalDiscount = $totalDiscount <= 0 ? 0 : ($totalDiscount / 100);
        //     //         $totalPrice = $productUnit->price - $totalDiscount;
        //     //     } else {
        //     //         $totalPrice = $productUnit->price - $discount->value;
        //     //     }

        //     //     $productUnit->price_discount = $totalPrice <= 0 ? 0 : $totalPrice;
        //     //     $productUnit->discount = $discount->value;
        //     //     $productUnit->is_percentage = $discount->is_percentage;
        //     // }

        //     unset($stockProductUnit->warehouse_id);
        //     unset($stockProductUnit->product_unit_id);

        //     unset($productUnit->product);
        //     unset($productUnit->uom_id);
        //     unset($productUnit->product_id);
        // });

        return response()->json($stockProductUnits);
    }
}
