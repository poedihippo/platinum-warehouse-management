<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ReceiveOrder\DetailStoreRequest;
use App\Http\Requests\Api\ReceiveOrder\DetailUpdateRequest;
use App\Http\Resources\ReceiveOrder\ReceiveOrderDetailResource;
use App\Interfaces\Services\ReceiveOrder\ReceiveOrderDetailServiceInterface;
use App\Models\ReceiveOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReceiveOrderDetailController extends BaseController
{
    public function __construct(private ReceiveOrderDetailServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:receive_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:receive_order_create', ['only' => 'store']);
        $this->middleware('permission:receive_order_edit', ['only' => 'update']);
        $this->middleware('permission:receive_order_delete', ['only' => 'destroy']);
        $this->middleware('permission:receive_order_verify_access', ['only' => 'verify']);
    }

    public function index(int $receiveOrderId)
    {
        return ReceiveOrderDetailResource::collection($this->service->paginateForReceiveOrder($receiveOrderId, $this->per_page));
    }

    public function show(int $receiveOrderId, int $receiveOrderDetailId)
    {
        return new ReceiveOrderDetailResource($this->service->findDetail($receiveOrderId, $receiveOrderDetailId));
    }

    public function store(int $receiveOrderId, DetailStoreRequest $request)
    {
        return new ReceiveOrderDetailResource($this->service->createDetail($receiveOrderId, $request->validated()));
    }

    public function update(int $receiveOrderId, $receiveOrderDetailId, DetailUpdateRequest $request)
    {
        $receiveOrderDetail = $this->service->findDetail($receiveOrderId, $receiveOrderDetailId);
        $this->service->update($receiveOrderDetail, $request->validated());

        return (new ReceiveOrderDetailResource($receiveOrderDetail))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function verify(int $receiveOrderId, $receiveOrderDetailId, Request $request)
    {
        $receiveOrderDetail = $this->service->findForVerify($receiveOrderId, $receiveOrderDetailId);

        $request->validate([
            'is_verified' => 'required|boolean',
        ]);

        $this->service->verify($receiveOrderDetail, $request->is_verified);

        return (new ReceiveOrderDetailResource($receiveOrderDetail))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(int $receiveOrderId, ReceiveOrderDetail $receiveOrderDetail)
    {
        $this->service->ensureDeletable($receiveOrderId, $receiveOrderDetail);

        try {
            $this->service->deleteDetail($receiveOrderDetail);
        } catch (\Throwable $th) {
            return response()->json($th, 500);
        }

        return $this->deletedResponse();
    }
}
