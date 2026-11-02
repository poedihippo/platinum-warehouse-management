<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Order\ConvertSORequest;
use App\Http\Requests\Api\Order\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\SalesOrder\SalesOrderResource;
use App\Interfaces\Services\Order\OrderServiceInterface;
use App\Models\SalesOrder;

class OrderController extends BaseController
{
    public function __construct(private OrderServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:order_create', ['only' => 'store']);
        $this->middleware('permission:order_edit', ['only' => 'update']);
        $this->middleware('permission:order_delete', ['only' => 'destroy']);
        $this->middleware('permission:order_print', ['only' => 'print']);
        $this->middleware('permission:order_export_xml', ['only' => 'exportXml']);
    }

    public function index()
    {
        return DefaultResource::collection($this->service->paginateIndex($this->per_page));
    }

    public function show(SalesOrder $order)
    {
        $order->load([
            'vouchers.category', 'payments', 'warehouse',
            'details' => fn ($q) => $q->with(['warehouse']),
            'user' => fn ($q) => $q->select('id', 'name', 'type'),
            'reseller' => fn ($q) => $q->select('id', 'name', 'type', 'type', 'email', 'phone', 'address'),
            'spg' => fn ($q) => $q->select('id', 'name', 'type', 'type', 'email', 'phone', 'address'),
        ])->loadCount('details');

        return new SalesOrderResource($order);
    }

    public function store(StoreRequest $request)
    {
        return new DefaultResource($this->service->store($request->validated(), $request->is_preview));
    }

    public function convertSalesOrder(SalesOrder $order, ConvertSORequest $request)
    {
        return new DefaultResource($this->service->convert($order, $request->validated(), $request->is_preview));
    }

    public function destroy(SalesOrder $order)
    {
        $this->service->destroyQuotation($order);

        return $this->deletedResponse();
    }
}
