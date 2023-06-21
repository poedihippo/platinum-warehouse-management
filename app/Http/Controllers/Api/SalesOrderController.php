<?php

namespace App\Http\Controllers\Api;

use App\Enums\SalesOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Http\Requests\Api\SalesOrderStoreRequest;
use App\Http\Requests\Api\SalesOrderUpdateRequest;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\UserDiscount;
use Barryvdh\DomPDF\Facade\Pdf;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class SalesOrderController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('sales_orders_access'), 403);
        $salesOrders = QueryBuilder::for(SalesOrder::withCount('details'))
            ->allowedFilters(['invoice_no', 'user_id', 'reseller_id', 'warehouse_id'])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'warehouse_id', 'created_at'])
            ->allowedIncludes(['details', 'warehouse', 'user'])
            ->paginate();

        return SalesOrderResource::collection($salesOrders);
    }

    public function show(SalesOrder $salesOrder)
    {
        abort_if(!auth()->user()->tokenCan('sales_order_create'), 403);
        return new SalesOrderResource($salesOrder->load(['details', 'user'])->loadCount('details'));
    }

    public function store(SalesOrderStoreRequest $request)
    {
        $items = $request->items ?? [];
        $totalPrice = collect($items)->sum('price') ?? 0;
        $data = [
            ...$request->validated(),
            'price' => $totalPrice
        ];

        DB::beginTransaction();
        try {
            $salesOrder = SalesOrder::create($data);

            for ($i = 0; $i < count($items); $i++) {
                $salesOrder->details()->create([
                    'product_unit_id' => $items[$i]['product_unit_id'],
                    'qty' => $items[$i]['qty'],
                ]);
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }

        return new SalesOrderResource($salesOrder);
    }

    public function update(SalesOrder $salesOrder, SalesOrderUpdateRequest $request)
    {
        // dump($request->all());
        dump($request->all());
        dd($request->validated());
        if ($salesOrder->deliveryOrder?->is_done) return response()->json(['message' => "Can't update SO if DO is already done"], 400);
        $salesOrder->update($request->validated());

        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(SalesOrder $salesOrder)
    {
        abort_if(!auth()->user()->tokenCan('sales_order_delete'), 403);
        $salesOrder->delete();
        return $this->deletedResponse();
    }

    public function updateStatus(SalesOrder $salesOrder, Request $request)
    {
        $request->validate([
            'status' => ['required', new EnumValue(SalesOrderStatus::class, false)],
        ]);

        $salesOrder->update([
            'status' => $request->status
        ]);

        return new SalesOrderResource($salesOrder);
    }

    public function print(SalesOrder $salesOrder)
    {
        $salesOrder->load([
            'reseller',
            'details' => fn ($q) => $q->with('productUnit.product')
        ]);

        $pdf = Pdf::loadView('pdf.salesOrders.salesOrder', ['salesOrder' => $salesOrder]);

        return $pdf->download('sales-order-' . $salesOrder->invoice_no . '.pdf');
    }

    public function exportXml(SalesOrder $salesOrder)
    {
        return response(view('xml.salesOrders.salesOrder')->with(compact('salesOrder')), 200, [
            'Content-Type' => 'application/xml', // use your required mime type
            'Content-Disposition' => 'attachment; filename="Sales Order ' . $salesOrder->invoice_no . '.xml"',
        ]);
    }

    public function productUnits(Request $request)
    {
        $userDiscount = UserDiscount::select('product_brand_id', 'value', 'is_percentage')->where('user_id', $request->customer_id)->get();

        $productUnits = ProductUnit::select('id', 'uom_id', 'product_id', 'name', 'price')
            ->with([
                'uom' => fn ($q) => $q->select('id', 'name'),
                'product' => fn ($q) => $q->select('id', 'product_brand_id')
            ])
            ->paginate();

        $productUnits->each(function ($productUnit) use ($userDiscount) {
            $productUnit->price_discount = $productUnit->price;

            $productBrandId = $productUnit?->product?->product_brand_id ?? null;
            if ($userDiscount->contains('product_brand_id', $productBrandId)) {
                $discount = $userDiscount->firstWhere('product_brand_id', $productBrandId);

                if ($discount->is_percentage) {
                    $totalDiscount = $productUnit->price * $discount->value;
                    $totalDiscount = $totalDiscount <= 0 ? 0 : ($totalDiscount / 100);
                    $totalPrice = $productUnit->price - $totalDiscount;
                } else {
                    $totalPrice = $productUnit->price - $discount->value;
                }

                $productUnit->price_discount = $totalPrice <= 0 ? 0 : $totalPrice;
            }

            unset($productUnit->product);
            unset($productUnit->uom_id);
            unset($productUnit->product_id);
        });

        return response()->json($productUnits);
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
    //     $userDiscount = UserDiscount::where('user_id', $request->customer_id)->where('product_brand_id', $productUnit?->product?->productBrand?->id)->first();
    //     if ($userDiscount) {
    //         $discount = $userDiscount->value;
    //         $isPercentage = $userDiscount->is_percentage;
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
