<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Http\Requests\Api\SalesOrderStoreRequest;
use App\Http\Requests\Api\SalesOrderUpdateRequest;
use App\Models\SalesOrder;
use App\Models\StockProductUnit;
use App\Models\UserDiscount;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SalesOrderController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:sales_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales_order_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales_order_create', ['only' => 'store']);
        $this->middleware('permission:sales_order_edit', ['only' => 'update']);
        $this->middleware('permission:sales_order_delete', ['only' => 'destroy']);
        $this->middleware('permission:sales_order_print', ['only' => 'print']);
        $this->middleware('permission:sales_order_export_xml', ['only' => 'exportXml']);
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
        if (!$salesOrder->details?->every(fn ($salesOrderDetail) => !$salesOrderDetail->deliveryOrderDetail))
            return response()->json(['message' => "DO harus dihapus terlebih dahulu sebelum mengedit SO"], 400);

        $salesOrder->raw_source = $request->validated();
        $salesOrder = SalesOrderService::updateOrder($salesOrder, (bool) $request->is_preview ?? false);
        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(int $id)
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_delete'), 403);
        $salesOrder = SalesOrder::findTenanted($id);
        if ($salesOrder->deliveryOrder?->is_done) return response()->json(['message' => "Can't update SO if DO is already done"], 400);

        $salesOrder->delete();
        return $this->deletedResponse();
    }

    public function print(int $id)
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_print'), 403);
        return SalesOrderService::print($id);
    }

    public function exportXml(int $id)
    {
        return SalesOrderService::exportXml($id);
    }

    public function productUnits(Request $request)
    {
        $userDiscounts = UserDiscount::select('product_brand_id', 'value', 'is_percentage')->where('user_id', $request->customer_id)->get();

        $query = StockProductUnit::select('id', 'warehouse_id', 'product_unit_id')
            ->withCount(['stocks' => fn ($q) => $q->whereAvailableStock()->whereNull('description')])
            ->with([
                'warehouse' => fn ($q) => $q->select('id', 'code'),
                'productUnit' => function ($q) {
                    $q->select('id', 'uom_id', 'product_id', 'packaging_id', 'name', 'price')
                        ->with([
                            'uom' => fn ($q) => $q->select('id', 'name'),
                            'product' => fn ($q) => $q->select('id', 'name', 'product_brand_id'),
                        ]);
                },
            ])
            ->having('stocks_count', '>', 0);

        $stockProductUnits = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::scope('product_unit'),
            ])
            ->paginate($this->per_page);

        $stockProductUnits->each(function ($stockProductUnit) use ($userDiscounts) {
            $productUnit = $stockProductUnit->productUnit;
            $productUnit->name = $productUnit->name . ' - ' . $stockProductUnit->warehouse?->code ?? '';
            $productUnit->price_discount = $productUnit->price;
            $productUnit->discount = 0;
            $productUnit->is_percentage = 1;

            $productBrandId = $productUnit?->product?->product_brand_id ?? null;
            if ($userDiscounts->contains('product_brand_id', $productBrandId)) {
                $discount = $userDiscounts->firstWhere('product_brand_id', $productBrandId);
                if ($discount->is_percentage) {
                    $totalDiscount = $productUnit->price * $discount->value;
                    $totalDiscount = $totalDiscount <= 0 ? 0 : ($totalDiscount / 100);
                    $totalPrice = $productUnit->price - $totalDiscount;
                } else {
                    $totalPrice = $productUnit->price - $discount->value;
                }

                $productUnit->price_discount = $totalPrice <= 0 ? 0 : $totalPrice;
                $productUnit->discount = $discount->value;
                $productUnit->is_percentage = $discount->is_percentage;
            }

            unset($stockProductUnit->warehouse_id);
            unset($stockProductUnit->product_unit_id);

            unset($productUnit->product);
            unset($productUnit->uom_id);
            unset($productUnit->product_id);
        });

        return response()->json($stockProductUnits);
    }
}
