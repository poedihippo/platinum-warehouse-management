<?php

namespace App\Http\Controllers\Api;

use App\Enums\SalesOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Http\Requests\Api\SalesOrderStoreRequest;
use App\Http\Requests\Api\SalesOrderUpdateRequest;
use App\Models\SalesOrder;
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
        abort_if(!user()->tokenCan('sales_orders_access'), 403);
        $salesOrders = QueryBuilder::for(SalesOrder::class)
            ->allowedIncludes(['details'])
            ->paginate();

        return SalesOrderResource::collection($salesOrders);
    }

    public function show(SalesOrder $salesOrder)
    {
        abort_if(!user()->tokenCan('sales_order_create'), 403);
        return new SalesOrderResource($salesOrder->load('details'));
    }

    public function store(SalesOrderStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            $salesOrder = SalesOrder::create($request->validated());

            for ($i = 0; $i < count($request->product_unit_ids); $i++) {
                $salesOrder->details()->create([
                    'sales_order_id' => $salesOrder->id,
                    'product_unit_id' => $request->product_unit_ids[$i],
                    'qty' => $request->qty[$i],
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
        // dd($request->validated());
        $salesOrder->update($request->validated());

        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(SalesOrder $salesOrder)
    {
        abort_if(!user()->tokenCan('sales_order_delete'), 403);
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

    public function print(SalesOrder $salesOrder, Request $request)
    {
        $type = isset($request->type) && $request->type === 'do' ? 'do' : 'so';

        $salesOrder->load([
            'reseller',
            'details' => function ($q) use ($type) {
                $q->with('productUnit.product');
                $q->when($type === 'do', fn ($q) => $q->withCount('salesOrderItems'));
            }
        ]);

        $pdf = Pdf::loadView('pdf.salesOrders.salesOrder', ['salesOrder' => $salesOrder, 'type' => $type]);

        return $pdf->download('sales-order-' . $salesOrder->code . '.pdf');
    }

    public function exportXml(SalesOrder $salesOrder)
    {
        // function export xml
        // $pdf = Pdf::loadView('pdf.salesOrders.salesOrder', ['salesOrder' => $salesOrder->load(['reseller', 'details' => fn ($q) => $q->with('productUnit.product')])]);
        // return $pdf->download('sales-order-' . $salesOrder->code . '.pdf');
        $cashAdvance = $cashAdvance->load(['details' => function ($q) {
            $q->where('is_summary', 0);
        }]);

        $salesOrder->load([
            'reseller',
            'details' => function ($q) use ($type) {
                $q->with('productUnit.product');
                $q->when($type === 'do', fn ($q) => $q->withCount('salesOrderItems'));
            }
        ]);
    }
}
