<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StockOpname\DetailStoreRequest;
use App\Http\Requests\Api\StockOpname\DetailUpdateRequest;
use App\Http\Resources\StockOpname\StockOpnameDetailResource;
use App\Interfaces\Services\StockOpname\StockOpnameDetailServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StockOpnameDetailController extends BaseController
{
    public function __construct(private StockOpnameDetailServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:stock_opname_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:stock_opname_create', ['only' => 'store']);
        $this->middleware('permission:stock_opname_edit', ['only' => 'update']);
        $this->middleware('permission:stock_opname_done', ['only' => 'done']);
    }

    public function index(int $stockOpnameId)
    {
        return StockOpnameDetailResource::collection($this->service->paginate($stockOpnameId, $this->per_page));
    }

    public function show(int $stockOpnameId, $stockOpnameDetailId)
    {
        return new StockOpnameDetailResource($this->service->find($stockOpnameId, $stockOpnameDetailId));
    }

    public function store(int $stockOpnameId, DetailStoreRequest $request)
    {
        return new StockOpnameDetailResource($this->service->create($stockOpnameId, $request->product_unit_id));
    }

    public function update(int $stockOpnameId, $id, DetailUpdateRequest $request)
    {
        $stockOpnameDetail = $this->service->updateDetail($stockOpnameId, $id, $request->validated());

        return (new StockOpnameDetailResource($stockOpnameDetail))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function scan(int $stockOpnameId, $stockOpnameDetailId, Request $request)
    {
        $request->validate([
            'stock_id' => 'required|exists:stocks,id',
        ]);

        $message = $this->service->scan($stockOpnameId, $stockOpnameDetailId, $request->stock_id, $request->is_scanned);

        return response()->json(['message' => $message], Response::HTTP_ACCEPTED);
    }

    public function done(int $stockOpnameId, string $id, Request $request)
    {
        $stockOpnameDetail = $this->service->setDone($stockOpnameId, $id, $request->is_done);

        $message = 'Data set as '.($stockOpnameDetail->is_done ? 'Done' : 'Pending');

        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
