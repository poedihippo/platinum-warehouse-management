<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductCategoryStoreRequest;
use App\Http\Requests\Api\ProductCategoryUpdateRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ProductCategoryController extends Controller
{
    public function __construct()
    {
        // $this->middleware('permission:product_category_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_category_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_category_create', ['only' => 'store']);
        $this->middleware('permission:product_category_edit', ['only' => 'update']);
        $this->middleware('permission:product_category_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        // abort_if(!auth()->user()->tokenCan('product_category_access'), 403);
        $productCategories = QueryBuilder::for(ProductCategory::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->paginate($this->per_page);

        return ProductCategoryResource::collection($productCategories);
    }

    public function show(ProductCategory $productCategory)
    {
        // abort_if(!auth()->user()->tokenCan('product_category_access'), 403);
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
        // abort_if(!auth()->user()->tokenCan('product_category_delete'), 403);
        $productCategory->delete();
        return $this->deletedResponse();
    }
}
