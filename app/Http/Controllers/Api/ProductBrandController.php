<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductBrandStoreRequest;
use App\Http\Resources\ProductBrandResource;
use App\Models\ProductBrand;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ProductBrandController extends Controller
{
    public function index()
    {
        // $productCategories = ProductBrand::simplePaginate();
        $productCategories = QueryBuilder::for(ProductBrand::class)
            ->allowedFilters(['name', 'description'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->simplePaginate();

        return ProductBrandResource::collection($productCategories);
    }

    public function show(ProductBrand $productBrand)
    {
        return new ProductBrandResource($productBrand);
    }

    public function store(ProductBrandStoreRequest $request)
    {
        $productBrand = ProductBrand::create($request->validated());

        return new ProductBrandResource($productBrand);
    }

    public function update(ProductBrand $productBrand, ProductBrandStoreRequest $request)
    {
        $productBrand->update($request->validated());

        return (new ProductBrandResource($productBrand))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ProductBrand $productBrand)
    {
        $productBrand->delete();
        return $this->deletedResponse();
    }
}
