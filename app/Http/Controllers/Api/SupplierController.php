<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Supplier\StoreRequest;
use App\Http\Requests\Api\Supplier\UpdateRequest;
use App\Http\Resources\Supplier\SupplierResource;
use App\Interfaces\Services\Supplier\SupplierServiceInterface;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;

class SupplierController extends BaseController
{
    public function __construct(private SupplierServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:supplier_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:supplier_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:supplier_create', ['only' => 'store']);
        $this->middleware('permission:supplier_edit', ['only' => 'update']);
        $this->middleware('permission:supplier_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $suppliers = $this->service->findAllPaginate(
            $this->per_page,
            null,
            [
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where('name', 'like', '%'.$value.'%')
                        ->orWhere('email', 'like', '%'.$value.'%')
                        ->orWhere('phone', 'like', '%'.$value.'%');
                }),
            ],
            [],
            ['id', 'name'],
        );

        return SupplierResource::collection($suppliers);
    }

    public function show(Supplier $supplier)
    {
        return new SupplierResource($supplier);
    }

    public function store(StoreRequest $request)
    {
        $supplier = $this->service->create($request->validated());

        return new SupplierResource($supplier);
    }

    public function update(Supplier $supplier, UpdateRequest $request)
    {
        $this->service->update($supplier, $request->validated());

        return (new SupplierResource($supplier))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Supplier $supplier)
    {
        $this->service->delete($supplier);

        return $this->deletedResponse();
    }
}
