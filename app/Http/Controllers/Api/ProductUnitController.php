<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductUnitStoreRequest;
use App\Http\Requests\Api\ProductUnitUpdateRequest;
use App\Http\Resources\ProductUnitResource;
use App\Http\Resources\SalesOrderDetailResource;
use App\Models\ProductUnit;
use App\Models\SalesOrderDetail;
use App\Models\User;
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

    public function userPrice(ProductUnit $productUnit, User $user)
    {
        $salesOrderDetails = SalesOrderDetail::select('id', 'product_unit_id', 'unit_price', 'created_at')
            ->whereHas('salesOrder', fn ($q) => $q->where('reseller_id', $user->id))
            ->where('product_unit_id', $productUnit->id)
            ->with('productUnit', fn ($q) => $q->select('id', 'code', 'name'))
            ->paginate();

        return SalesOrderDetailResource::collection($salesOrderDetails);
    }
}
