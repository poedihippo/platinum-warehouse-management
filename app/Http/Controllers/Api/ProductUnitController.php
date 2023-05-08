<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductUnitStoreRequest;
use App\Http\Resources\ProductUnitResource;
use App\Models\ProductUnit;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ProductUnitController extends Controller
{
    public function index()
    {
        // $productCategories = ProductCategory::simplePaginate();
        $productunits = QueryBuilder::for(ProductUnit::class)
            ->allowedFilters(['name', 'description'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->simplePaginate();

        return ProductUnitResource::collection($productunits);
    }

    public function show(ProductUnit $productUnit)
    {
        return new ProductUnitResource($productUnit);
    }

    public function store(ProductUnitStoreRequest $request)
    {
        $productUnit = ProductUnit::create($request->validated());

        return new ProductUnitResource($productUnit);
    }

    public function update(ProductUnit $productUnit, ProductUnitStoreRequest $request)
    {
        $productUnit->update($request->validated());

        return (new ProductUnitResource($productUnit))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ProductUnit $productUnit)
    {
        $productUnit->delete();
        return $this->deletedResponse();
    }
}
