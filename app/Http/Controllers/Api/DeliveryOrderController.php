<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\DeliveryOrder\AttachRequest;
use App\Http\Requests\Api\DeliveryOrder\ReturnRequest;
use App\Http\Requests\Api\DeliveryOrder\StoreRequest;
use App\Http\Requests\Api\DeliveryOrder\UpdateRequest;
use App\Http\Requests\Api\SalesOrderItemStoreRequest;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\DeliveryOrder\DeliveryOrderResource;
use App\Http\Resources\SalesOrderItemResource;
use App\Interfaces\Services\DeliveryOrder\DeliveryOrderServiceInterface;
use App\Models\DeliveryOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeliveryOrderController extends BaseController
{
    public function __construct(private DeliveryOrderServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:delivery_order_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:delivery_order_create', ['only' => 'store']);
        $this->middleware('permission:delivery_order_edit', ['only' => 'update']);
        $this->middleware('permission:delivery_order_delete', ['only' => 'destroy']);
        $this->middleware('permission:delivery_order_print', ['only' => 'print']);
        $this->middleware('permission:sales_order_export_xml', ['only' => 'exportXml']);
        $this->middleware('permission:delivery_order_done', ['only' => 'done']);
    }

    public function index()
    {
        return DeliveryOrderResource::collection($this->service->paginateIndex($this->per_page));
    }

    public function show($id)
    {
        return new DeliveryOrderResource($this->service->findForShow($id));
    }

    public function getDoBySoDetail($id)
    {
        return new DefaultResource($this->service->findBySalesOrderDetail($id));
    }

    public function store(StoreRequest $request)
    {
        $deliveryOrder = $this->service->createDeliveryOrder($request->validated());

        return response()->json(['data' => $deliveryOrder], 201);
    }

    public function update($id, UpdateRequest $request)
    {
        $deliveryOrder = $this->service->findTenanted($id);
        $this->service->update($deliveryOrder, $request->validated());

        return response()->json(['data' => $deliveryOrder], 200);
    }

    public function destroy($id)
    {
        $this->service->destroyDeliveryOrder($this->service->findTenanted($id));

        return $this->deletedResponse();
    }

    public function print($id)
    {
        return $this->service->print($id);
    }

    public function exportXml($id)
    {
        return $this->service->exportXml($id);
    }

    public function verification(SalesOrderItemStoreRequest $request, int $id, DeliveryOrderDetail $deliveryOrderDetail)
    {
        return new SalesOrderItemResource($this->service->verify($id, $deliveryOrderDetail, $request->stock_id));
    }

    public function done($id, Request $request)
    {
        $request->validate(['is_done' => 'required|boolean']);

        $deliveryOrder = $this->service->setDone($id, $request->is_done);

        $message = 'Data set as '.($deliveryOrder->is_done ? 'Done' : 'Pending');

        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function attach($id, AttachRequest $request)
    {
        $deliveryOrder = $this->service->findTenanted($id, ['id']);

        $result = $this->service->attachDetails($deliveryOrder, $request->details);

        if (! empty($result['errors'])) {
            return response()->json([
                'message' => 'Beberapa item gagal ditambahkan.',
                'errors' => $result['errors'],
            ], 422);
        }

        return response()->json([
            'message' => $result['count'].' Sales order berhasil ditambahkan ke delivery order',
        ]);
    }

    public function return(ReturnRequest $request, $id)
    {
        if ($rejection = $this->service->returnStocks($id, $request->ids, $request->is_delete)) {
            return $rejection;
        }

        return response()->json([
            'message' => count($request->ids).' stocks returned '.($request->is_delete ? 'and deleted' : '').' successfully',
        ]);
    }
}
