<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductUnitStoreRequest;
use App\Http\Requests\Api\ProductUnitUpdateRequest;
use App\Http\Resources\ProductUnitResource;
use App\Models\ProductUnit;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductUnitController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('product_unit_access'), 403);
        $productUnits = QueryBuilder::for(ProductUnit::with('product'))
            ->allowedFilters([
                'product_id', 'name',
                AllowedFilter::scope('product_brand_id', 'whereProductBrandId'),
                AllowedFilter::scope('product_category_id', 'whereProductCategoryId'),
            ])
            ->allowedSorts(['id', 'product_id', 'name', 'price', 'created_at'])
            ->paginate();

        return ProductUnitResource::collection($productUnits);
    }

    public function show(ProductUnit $productUnit)
    {
        abort_if(!auth()->user()->tokenCan('product_unit_access'), 403);
        return new ProductUnitResource($productUnit);
    }

    public function store(ProductUnitStoreRequest $request)
    {
        $productUnit = ProductUnit::create($request->validated());

        return new ProductUnitResource($productUnit);
    }

    public function update(ProductUnit $productUnit, ProductUnitUpdateRequest $request)
    {
        $productUnit->update($request->validated());

        return (new ProductUnitResource($productUnit))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ProductUnit $productUnit)
    {
        abort_if(!auth()->user()->tokenCan('product_unit_delete'), 403);
        $productUnit->delete();
        return $this->deletedResponse();
    }
}
