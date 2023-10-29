<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Http\Requests\Api\SalesOrderStoreRequest;
use App\Http\Requests\Api\SalesOrderUpdateRequest;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\StockProductUnit;
use App\Models\UserDiscount;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SalesOrderController extends Controller
{
    public function __construct()
    {
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
        // abort_if(!auth()->user()->tokenCan('sales_order_access'), 403);
        $salesOrders = QueryBuilder::for(SalesOrder::withCount('details'))
            ->allowedFilters([
                'invoice_no',
                'user_id',
                'reseller_id',
                'warehouse_id',
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'warehouse_id', 'created_at'])
            ->allowedIncludes(['details', 'warehouse', 'user'])
            ->paginate();

        return SalesOrderResource::collection($salesOrders);
    }

    public function show(SalesOrder $salesOrder)
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_access'), 403);
        return new SalesOrderResource($salesOrder->load(['details' => fn($q) => $q->with(['warehouse', 'packaging']), 'user'])->loadCount('details'));
    }

    public function store(SalesOrderStoreRequest $request)
    {
        // $items = $request->items ?? [];
        // $totalPrice = collect($items)->sum('total_price') ?? 0;
        // $shipmentFee = $request->validated()['shipment_fee'];
        // $totalPrice += $shipmentFee;

        // $data = [
        //     ...$request->validated(),
        //     'price' => $totalPrice
        // ];

        // // BE total price validation
        // if (SalesOrderService::validateTotalPrice($totalPrice, $shipmentFee, $items) === false)
        //     return response()->json(['message' => "Prices don't match"], 400);

        // DB::beginTransaction();
        // try {
        //     $salesOrder = SalesOrder::create($data);

        //     for ($i = 0; $i < count($items); $i++) {
        //         $productUnit = ProductUnit::withTrashed()->find($items[$i]['product_unit_id']);
        //         if (!$productUnit)
        //             return response()->json(['message' => 'Product ' . $items[$i]['product_unit_id'] . ' not found on system. Please add first'], 400);

        //         $salesOrder->details()->create([
        //             'product_unit_id' => $items[$i]['product_unit_id'],
        //             'packaging_id' => empty($items[$i]['packaging_id']) ? null : $items[$i]['packaging_id'],
        //             'qty' => $items[$i]['qty'],
        //             'unit_price' => $items[$i]['unit_price'] ?? $productUnit->price ?? 0,
        //             'discount' => $items[$i]['discount'] ?? 0,
        //             'tax' => isset($items[$i]['tax']) && $items[$i]['tax'] == 1 ? 11 : 0,
        //             'total_price' => $items[$i]['total_price'] ?? 0,
        //             'warehouse_id' => $items[$i]['warehouse_id'],
        //         ]);
        //     }
        //     DB::commit();
        // } catch (\Throwable $th) {
        //     DB::rollBack();
        //     return response()->json(['message' => $th->getMessage()], 500);
        // }

        $salesOrder = SalesOrderService::processOrder(SalesOrder::make(['raw_source' => $request->validated()]));
        return new SalesOrderResource($salesOrder);
    }

    public function update(SalesOrder $salesOrder, SalesOrderUpdateRequest $request)
    {
        if (!$salesOrder->details?->every(fn($salesOrderDetail) => !$salesOrderDetail->deliveryOrderDetail))
            return response()->json(['message' => "DO must be deleted first before editing SO"], 400);

        $items = $request->items ?? [];
        $totalPrice = collect($items)->sum('total_price') ?? 0;
        $shipmentFee = $request->validated()['shipment_fee'];
        $totalPrice += $shipmentFee;

        $data = [
            ...$request->validated(),
            'price' => $totalPrice
        ];

        // BE total price validation
        if (SalesOrderService::validateTotalPrice($totalPrice, $shipmentFee, $items) === false)
            return response()->json(['message' => "Prices don't match"], 400);

        DB::beginTransaction();
        try {
            $oldDetails = $salesOrder->details;
            $salesOrder->update($data);

            for ($i = 0; $i < count($items); $i++) {
                $productUnit = ProductUnit::withTrashed()->find($items[$i]['product_unit_id']);
                if (!$productUnit)
                    return response()->json(['message' => 'Product ' . $items[$i]['product_unit_id'] . ' not found on system. Please add first'], 400);

                $salesOrder->details()->create([
                    // 'product_unit_id' => $items[$i]['product_unit_id'],
                    // 'qty' => $items[$i]['qty'],
                    // 'unit_price' => $productUnit->price ?? 0,
                    // 'discount' => $items[$i]['discount'] ?? 0,
                    // 'total_price' => $items[$i]['price'] ?? 0,
                    'product_unit_id' => $items[$i]['product_unit_id'],
                    'packaging_id' => empty($items[$i]['packaging_id']) ? null : $items[$i]['packaging_id'],
                    'qty' => $items[$i]['qty'],
                    'unit_price' => $items[$i]['unit_price'] ?? $productUnit->price ?? 0,
                    'discount' => $items[$i]['discount'] ?? 0,
                    'tax' => isset($items[$i]['tax']) && $items[$i]['tax'] == 1 ? 11 : 0,
                    'total_price' => $items[$i]['total_price'] ?? 0,
                    'warehouse_id' => $items[$i]['warehouse_id'],
                ]);
            }

            $oldDetails->each->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }

        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(SalesOrder $salesOrder)
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_delete'), 403);
        if ($salesOrder->deliveryOrder?->is_done)
            return response()->json(['message' => "Can't update SO if DO is already done"], 400);
        $salesOrder->delete();
        return $this->deletedResponse();
    }

    public function print(SalesOrder $salesOrder)
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_print'), 403);
        $salesOrder->load([
            'reseller',
            'details' => fn($q) => $q->with('productUnit.product')
        ]);
        $salesOrderDetails = $salesOrder->details->chunk(10);

        $lastOrderDetailsKey = $salesOrderDetails->keys()->last();
        $maxProductsBlackSpace = 10;

        $spellTotalPrice = \NumberToWords\NumberToWords::transformNumber('en', $salesOrder->price);
        $bankTransferInfo = \App\Services\SettingService::bankTransferInfo();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setPaper('a4', 'landscape')->loadView('pdf.salesOrders.salesOrder', ['salesOrder' => $salesOrder, 'salesOrderDetails' => $salesOrderDetails, 'maxProductsBlackSpace' => $maxProductsBlackSpace, 'lastOrderDetailsKey' => $lastOrderDetailsKey, 'spellTotalPrice' => $spellTotalPrice, 'bankTransferInfo' => $bankTransferInfo]);

        return $pdf->download('sales-order-' . $salesOrder->invoice_no . '.pdf');
    }

    public function exportXml(SalesOrder $salesOrder)
    {
        $salesOrder->load(['reseller', 'details' => fn($q) => $q->with('packaging', 'productUnit')]);
        // abort_if(!auth()->user()->tokenCan('sales_order_export_xml'), 403);
        return response(view('xml.salesOrders.salesOrder')->with(compact('salesOrder')), 200, [
            'Content-Type' => 'application/xml',
            // use your required mime type
            'Content-Disposition' => 'attachment; filename="Sales Order ' . $salesOrder->invoice_no . '.xml"',
        ]);
    }

    public function productUnits(Request $request)
    {
        $userDiscounts = UserDiscount::select('product_brand_id', 'value', 'is_percentage')->where('user_id', $request->customer_id)->get();

        $query = StockProductUnit::select('id', 'warehouse_id', 'product_unit_id')
            ->withCount(['stocks' => fn($q) => $q->whereAvailableStock()->whereNull('description')])
            ->with([
                'warehouse' => fn($q) => $q->select('id', 'code'),
                'productUnit' => function ($q) {
                    $q->select('id', 'uom_id', 'product_id', 'packaging_id', 'name', 'price')
                        ->with([
                            'uom' => fn($q) => $q->select('id', 'name'),
                            'product' => fn($q) => $q->select('id', 'name', 'product_brand_id'),
                        ]);
                },
            ])
            ->having('stocks_count', '>', 0);

        $stockProductUnits = QueryBuilder::for($query)
            ->allowedFilters(AllowedFilter::scope('product_unit'))
            ->paginate();

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

    // public function getPrice(Request $request)
    // {
    //     $request->validate([
    //         'customer_id' => 'required',
    //         'product_unit_id' => 'required',
    //         'qty' => 'required|numeric',
    //     ]);

    //     $productUnit = ProductUnit::findOrFail($request->product_unit_id);

    //     $originalPrice = ($productUnit->price ?? 0) * ($request->qty ?? 0);
    //     $totalPrice = 0;
    //     $discount = 0;
    //     $isPercentage = 0;
    //     $userDiscounts = UserDiscount::where('user_id', $request->customer_id)->where('product_brand_id', $productUnit?->product?->productBrand?->id)->first();
    //     if ($userDiscounts) {
    //         $discount = $userDiscounts->value;
    //         $isPercentage = $userDiscounts->is_percentage;
    //     }

    //     if ($isPercentage) {
    //         $totalDiscount = $originalPrice * $discount;
    //         $totalDiscount = $totalDiscount <= 0 ? 0 : ($totalDiscount / 100);
    //         $totalPrice = $originalPrice - $totalDiscount;
    //     } else {
    //         $totalPrice = $originalPrice - $discount;
    //     }

    //     $totalPrice = $totalPrice <= 0 ? 0 : $totalPrice;

    //     return response()->json([
    //         'price' => $totalPrice
    //     ]);
    // }
}
