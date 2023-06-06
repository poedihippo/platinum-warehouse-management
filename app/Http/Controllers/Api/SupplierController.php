<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupplierStoreRequest;
use App\Http\Requests\Api\SupplierUpdateRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class SupplierController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('suppliers_access'), 403);
        $suppliers = QueryBuilder::for(Supplier::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name'])
            ->paginate();

        return SupplierResource::collection($suppliers);
    }

    public function show(Supplier $supplier)
    {
        abort_if(!auth()->user()->tokenCan('supplier_view'), 403);
        return new SupplierResource($supplier);
    }

    public function store(SupplierStoreRequest $request)
    {
        $supplier = Supplier::create($request->validated());

        return new SupplierResource($supplier);
    }

    public function update(Supplier $supplier, SupplierUpdateRequest $request)
    {
        $supplier->update($request->validated());

        return (new SupplierResource($supplier))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Supplier $supplier)
    {
        abort_if(!auth()->user()->tokenCan('supplier_delete'), 403);
        $supplier->delete();
        return $this->deletedResponse();
    }
}
