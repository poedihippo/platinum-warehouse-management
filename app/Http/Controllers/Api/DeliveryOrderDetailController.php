<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\DeliveryOrder\DetailUpdateRequest;
use App\Http\Resources\DeliveryOrder\DeliveryOrderDetailResource;
use App\Http\Resources\SalesOrderItemResource;
use App\Interfaces\Services\DeliveryOrder\DeliveryOrderDetailServiceInterface;

class DeliveryOrderDetailController extends BaseController
{
    public function __construct(private DeliveryOrderDetailServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:delivery_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:delivery_order_delete', ['only' => ['destroy', 'reset']]);
    }

    public function index(int $deliveryOrderId)
    {
        return DeliveryOrderDetailResource::collection($this->service->paginate($deliveryOrderId, $this->per_page));
    }

    public function show(int $deliveryOrderId, int $deliveryOrderDetailId)
    {
        return new DeliveryOrderDetailResource($this->service->find($deliveryOrderId, $deliveryOrderDetailId));
    }

    public function items(int $deliveryOrderId, int $deliveryOrderDetailId)
    {
        return SalesOrderItemResource::collection($this->service->paginateItems($deliveryOrderDetailId, $this->per_page));
    }

    public function update(int $deliveryOrderId, int $deliveryOrderDetailId, DetailUpdateRequest $request)
    {
        return new DeliveryOrderDetailResource($this->service->updateQty($deliveryOrderId, $deliveryOrderDetailId, $request->qty));
    }

    public function destroy(int $deliveryOrderId, int $deliveryOrderDetailId)
    {
        $this->service->deleteDetail($deliveryOrderId, $deliveryOrderDetailId);

        return $this->deletedResponse();
    }

    public function resetVerifiedStock(int $deliveryOrderId, int $deliveryOrderDetailId)
    {
        return $this->updatedResponse($this->service->resetVerifiedStock($deliveryOrderId, $deliveryOrderDetailId));
    }
}
