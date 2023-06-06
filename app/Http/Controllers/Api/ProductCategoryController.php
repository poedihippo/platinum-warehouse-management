<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductCategoryStoreRequest;
use App\Http\Requests\ProductCategoryUpdateRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ProductCategoryController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('product_categories_access'), 403);
        $productCategories = QueryBuilder::for(ProductCategory::class)
            ->allowedFilters(['name', 'description'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->paginate();

        return ProductCategoryResource::collection($productCategories);
    }

    public function show(ProductCategory $productCategory)
    {
        abort_if(!auth()->user()->tokenCan('product_category_view'), 403);
        return new ProductCategoryResource($productCategory);
    }

    public function store(ProductCategoryStoreRequest $request)
    {
        $productCategory = ProductCategory::create($request->validated());

        return new ProductCategoryResource($productCategory);
    }

    public function update(ProductCategory $productCategory, ProductCategoryUpdateRequest $request)
    {
        $productCategory->update($request->validated());

        return (new ProductCategoryResource($productCategory))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ProductCategory $productCategory)
    {
        abort_if(!auth()->user()->tokenCan('product_category_delete'), 403);
        $productCategory->delete();
        return $this->deletedResponse();
    }
}
