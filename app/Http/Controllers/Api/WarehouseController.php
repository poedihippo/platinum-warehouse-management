<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Warehouse\StoreRequest;
use App\Http\Requests\Api\Warehouse\UpdateRequest;
use App\Http\Resources\DefaultResource;
use App\Interfaces\Services\Warehouse\WarehouseServiceInterface;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;

class WarehouseController extends BaseController
{
    public function __construct(private WarehouseServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:warehouse_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:warehouse_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:warehouse_create', ['only' => 'store']);
        $this->middleware('permission:warehouse_edit', ['only' => 'update']);
        $this->middleware('permission:warehouse_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $warehouses = $this->service->findAllPaginate(
            $this->per_page,
            fn ($query) => $query->tenanted(),
            [
                'name',
                AllowedFilter::exact('is_for_marketplace'),
            ],
            [],
            ['id', 'name', 'is_for_marketplace', 'created_at'],
        );

        return DefaultResource::collection($warehouses);
    }

    public function show($id)
    {
        $warehouse = $this->service->findTenanted($id);

        return new DefaultResource($warehouse);
    }

    public function store(StoreRequest $request)
    {
        $warehouse = $this->service->create($request->validated());

        return new DefaultResource($warehouse);
    }

    public function update($id, UpdateRequest $request)
    {
        $warehouse = $this->service->findTenanted($id);
        $this->service->update($warehouse, $request->validated());

        return (new DefaultResource($warehouse))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy($id)
    {
        $warehouse = $this->service->findTenanted($id);
        $this->service->delete($warehouse);

        return $this->deletedResponse();
    }
}
