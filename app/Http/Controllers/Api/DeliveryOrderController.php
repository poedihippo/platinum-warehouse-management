<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryOrderResource;
use App\Http\Requests\Api\DeliveryOrderStoreRequest;
use App\Http\Requests\Api\DeliveryOrderUpdateRequest;
use App\Models\DeliveryOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class DeliveryOrderController extends Controller
{
    public function index()
    {
        abort_if(!user()->tokenCan('delivery_orders_access'), 403);
        $deliveryOrders = QueryBuilder::for(DeliveryOrder::class)
            ->paginate();

        return DeliveryOrderResource::collection($deliveryOrders);
    }

    public function show(DeliveryOrder $deliveryOrder)
    {
        abort_if(!user()->tokenCan('delivery_order_create'), 403);
        return new DeliveryOrderResource($deliveryOrder->load('details'));
    }

    public function store(DeliveryOrderStoreRequest $request)
    {
        $deliveryOrder = DeliveryOrder::create($request->validated());

        return new DeliveryOrderResource($deliveryOrder);
    }

    public function update(DeliveryOrder $deliveryOrder, DeliveryOrderUpdateRequest $request)
    {
        // dump($request->all());
        // dd($request->validated());
        $deliveryOrder->update($request->validated());

        return (new DeliveryOrderResource($deliveryOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(DeliveryOrder $deliveryOrder)
    {
        abort_if(!user()->tokenCan('delivery_order_delete'), 403);
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
}
