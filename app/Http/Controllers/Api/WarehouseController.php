<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WarehouseStoreRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = QueryBuilder::for(Warehouse::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->simplePaginate();

        return WarehouseResource::collection($warehouses);
    }

    public function show(Warehouse $warehouse)
    {
        return new WarehouseResource($warehouse);
    }

    public function store(WarehouseStoreRequest $request)
    {
        $warehouse = Warehouse::create($request->validated());

        return new WarehouseResource($warehouse);
    }

    public function update(Warehouse $warehouse, WarehouseStoreRequest $request)
    {
        $warehouse->update($request->validated());

        return (new WarehouseResource($warehouse))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();
        return $this->deletedResponse();
    }
}
