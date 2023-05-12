<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductCategoryStoreRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $productCategories = QueryBuilder::for(ProductCategory::class)
            ->allowedFilters(['name', 'description'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->simplePaginate();

        return ProductCategoryResource::collection($productCategories);
    }

    public function show(ProductCategory $productCategory)
    {
        return new ProductCategoryResource($productCategory);
    }

    public function store(ProductCategoryStoreRequest $request)
    {
        $productCategory = ProductCategory::create($request->validated());

        return new ProductCategoryResource($productCategory);
    }

    public function update(ProductCategory $productCategory, ProductCategoryStoreRequest $request)
    {
        $productCategory->update($request->validated());

        return (new ProductCategoryResource($productCategory))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();
        return $this->deletedResponse();
    }
}
