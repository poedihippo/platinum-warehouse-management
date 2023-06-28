<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductBrandStoreRequest;
use App\Http\Requests\ProductBrandUpdateRequest;
use App\Http\Resources\ProductBrandResource;
use App\Models\ProductBrand;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ProductBrandController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('product_brand_access'), 403);
        $productCategories = QueryBuilder::for(ProductBrand::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->paginate();

        return ProductBrandResource::collection($productCategories);
    }

    public function show(ProductBrand $productBrand)
    {
        abort_if(!auth()->user()->tokenCan('product_brand_access'), 403);
        return new ProductBrandResource($productBrand);
    }

    public function store(ProductBrandStoreRequest $request)
    {
        $productBrand = ProductBrand::create($request->validated());

        return new ProductBrandResource($productBrand);
    }

    public function update(ProductBrand $productBrand, ProductBrandUpdateRequest $request)
    {
        $productBrand->update($request->validated());

        return (new ProductBrandResource($productBrand))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ProductBrand $productBrand)
    {
        abort_if(!auth()->user()->tokenCan('product_brand_delete'), 403);
        $productBrand->delete();
        return $this->deletedResponse();
    }
}
