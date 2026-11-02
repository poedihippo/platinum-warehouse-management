<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StockOpname\StoreRequest;
use App\Http\Resources\StockOpname\StockOpnameResource;
use App\Interfaces\Services\StockOpname\StockOpnameServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;

class StockOpnameController extends BaseController
{
    public function __construct(private StockOpnameServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:stock_opname_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:stock_opname_create', ['only' => 'store']);
        $this->middleware('permission:stock_opname_edit', ['only' => 'update']);
        $this->middleware('permission:stock_opname_delete', ['only' => 'destroy']);
        $this->middleware('permission:stock_opname_done', ['only' => ['done', 'setDone']]);
    }

    public function index()
    {
        $stockOpnames = $this->service->findAllPaginate(
            $this->per_page,
            fn ($query) => $query->tenanted(),
            [
                AllowedFilter::exact('warehouse_id'),
                'description',
                'is_done',
            ],
            [],
            ['id', 'description', 'is_done', 'warehouse_id', 'created_at'],
        );

        return StockOpnameResource::collection($stockOpnames);
    }

    public function show($id)
    {
        return new StockOpnameResource($this->service->findTenanted($id));
    }

    public function store(StoreRequest $request)
    {
        return new StockOpnameResource($this->service->create($request->validated()));
    }

    public function destroy($id)
    {
        $this->service->delete($this->service->findTenanted($id));

        return $this->deletedResponse();
    }

    public function done($id, Request $request)
    {
        $request->validate(['is_done' => 'required|boolean']);

        $stockOpname = $this->service->setDone($this->service->findTenanted($id), $request->is_done);

        $message = 'Data set as '.($stockOpname->is_done ? 'Done' : 'Pending');

        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function setDone($id, Request $request)
    {
        $request->validate(['is_done' => 'required|boolean']);

        $this->service->setAllDetailsDone($this->service->findTenanted($id), $request->is_done);

        $message = 'All data set as '.($request->is_done ? 'Done' : 'Pending');

        return response()->json(['message' => $message])->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
