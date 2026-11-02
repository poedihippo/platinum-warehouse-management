<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ReceiveOrder\StoreRequest;
use App\Http\Requests\Api\ReceiveOrder\UpdateRequest;
use App\Http\Resources\ReceiveOrder\ReceiveOrderResource;
use App\Interfaces\Services\ReceiveOrder\ReceiveOrderServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;

class ReceiveOrderController extends BaseController
{
    public function __construct(private ReceiveOrderServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:receive_order_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:receive_order_create', ['only' => 'store']);
        $this->middleware('permission:receive_order_edit', ['only' => 'update']);
        $this->middleware('permission:receive_order_delete', ['only' => 'destroy']);
        $this->middleware('permission:receive_order_done', ['only' => 'done']);
    }

    public function index()
    {
        $receiveOrders = $this->service->findAllPaginate(
            $this->per_page,
            fn ($query) => $query->tenanted()->withCount('details'),
            [
                'invoice_no',
                'is_done',
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ],
            ['details', 'user'],
            ['id', 'invoice_no', 'user_id', 'supplier_id', 'warehouse_id', 'created_at'],
        );

        return ReceiveOrderResource::collection($receiveOrders);
    }

    public function show($id)
    {
        $receiveOrder = $this->service->findTenanted($id);

        return new ReceiveOrderResource($receiveOrder->load('details')->loadCount('details'));
    }

    public function store(StoreRequest $request)
    {
        return new ReceiveOrderResource($this->service->createFromXml($request->file, $request->receive_datetime));
    }

    public function update($id, UpdateRequest $request)
    {
        $receiveOrder = $this->service->findTenanted($id);
        dump($request->all());
        dd($request->validated());
        $receiveOrder->update($request->validated());

        return (new ReceiveOrderResource($receiveOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy($id)
    {
        $receiveOrder = $this->service->findTenanted($id);
        $this->service->deleteIfUnverified($receiveOrder);

        return $this->deletedResponse();
    }

    public function done($id, Request $request)
    {
        $receiveOrder = $this->service->findTenanted($id);
        $request->validate(['is_done' => 'required|boolean']);

        $receiveOrder = $this->service->setDone($receiveOrder, $request->is_done);

        $message = 'Receive order set as '.($receiveOrder->is_done ? 'Done' : 'Pending');

        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
