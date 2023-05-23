<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Http\Requests\Api\SalesOrderStoreRequest;
use App\Http\Requests\Api\SalesOrderUpdateRequest;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
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
        // dd(user());
        $salesOrder = SalesOrder::create([
            'user_id' => user()->id,
            'reseller_id' => $request->reseller_id,
            'transaction_date' => $request->transaction_date,
            'status' => $request->status
        ]);
        for ($i = 0; $i < count($request->product_unit_ids); $i++) {
            $salesOrder->details()->create([
                'sales_order_id' => $salesOrder->id,
                'product_unit_id' => $request->product_unit_ids[$i],
                'qty' => $request->qty[$i],
            ]);
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
}
