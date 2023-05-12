<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductStoreRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    public function index()
    {
        $products = QueryBuilder::for(Product::with(['productCategory', 'productBrand']))
            ->allowedFilters(['product_category_id', 'product_brand_id', 'name', 'description'])
            ->allowedSorts(['id', 'product_category_id', 'product_brand_id', 'name', 'created_at'])
            ->simplePaginate();

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        return new ProductResource($product);
    }

    public function store(ProductStoreRequest $request)
    {
        $product = Product::create($request->validated());

        return new ProductResource($product);
    }

    public function update(Product $product, ProductStoreRequest $request)
    {
        $product->update($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return $this->deletedResponse();
    }
}
