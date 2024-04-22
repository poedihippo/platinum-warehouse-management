<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WarehouseStoreRequest;
use App\Http\Requests\Api\WarehouseUpdateRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class WarehouseController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:warehouse_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:warehouse_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:warehouse_create', ['only' => 'store']);
        $this->middleware('permission:warehouse_edit', ['only' => 'update']);
        $this->middleware('permission:warehouse_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        // abort_if(!auth()->user()->tokenCan('warehouse_access'), 403);
        /** @var \App\Models\User $user */
        $user = auth('sanctum')->user();
        $warehouses = QueryBuilder::for(Warehouse::whereIn('id', $user->warehouses()->pluck('warehouse_id') ?? []))
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->paginate($this->per_page);

        return WarehouseResource::collection($warehouses);
    }

    public function show(Warehouse $warehouse)
    {
        // abort_if(!auth()->user()->tokenCan('warehouse_access'), 403);
        return new WarehouseResource($warehouse);
    }

    public function store(WarehouseStoreRequest $request)
    {
        $warehouse = Warehouse::create($request->validated());

        return new WarehouseResource($warehouse);
    }

    public function update(Warehouse $warehouse, WarehouseUpdateRequest $request)
    {
        $warehouse->update($request->validated());

        return (new WarehouseResource($warehouse))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Warehouse $warehouse)
    {
        // abort_if(!auth()->user()->tokenCan('warehouse_delete'), 403);
        $warehouse->delete();
        return $this->deletedResponse();
    }
}
