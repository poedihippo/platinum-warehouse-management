<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WarehouseStoreRequest;
use App\Http\Requests\Api\WarehouseUpdateRequest;
use App\Http\Resources\DefaultResource;
use App\Models\Warehouse;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WarehouseController extends Controller
{
    public function __construct()
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
        // abort_if(!auth('sanctum')->user()->tokenCan('warehouse_access'), 403);
        $warehouses = QueryBuilder::for(Warehouse::tenanted())
            ->allowedFilters([
                'name',
                AllowedFilter::exact('is_for_marketplace')
            ])
            ->allowedSorts(['id', 'name', 'is_for_marketplace', 'created_at'])
            ->paginate($this->per_page);

        return DefaultResource::collection($warehouses);
    }

    public function show($id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('warehouse_access'), 403);
        $warehouse = Warehouse::findTenanted($id);

        return new DefaultResource($warehouse);
    }

    public function store(WarehouseStoreRequest $request)
    {
        $warehouse = Warehouse::create($request->validated());

        return new DefaultResource($warehouse);
    }

    public function update($id, WarehouseUpdateRequest $request)
    {
        $warehouse = Warehouse::findTenanted($id);
        $warehouse->update($request->validated());

        return (new DefaultResource($warehouse))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy($id)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('warehouse_delete'), 403);
        $warehouse = Warehouse::findTenanted($id);
        $warehouse->delete();

        return $this->deletedResponse();
    }
}
