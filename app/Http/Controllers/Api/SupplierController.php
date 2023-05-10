<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupplierStoreRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = QueryBuilder::for(Supplier::class)
            ->allowedFilters(['product_category_id', 'product_brand_id', 'name', 'description'])
            ->allowedSorts(['id', 'product_category_id', 'product_brand_id', 'name', 'created_at'])
            ->simplePaginate();

        return SupplierResource::collection($suppliers);
    }

    public function show(Supplier $supplier)
    {
        return new SupplierResource($supplier);
    }

    public function store(SupplierStoreRequest $request)
    {
        $supplier = Supplier::create($request->validated());

        return new SupplierResource($supplier);
    }

    public function update(Supplier $supplier, SupplierStoreRequest $request)
    {
        $supplier->update($request->validated());

        return (new SupplierResource($supplier))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return $this->deletedResponse();
    }
}
