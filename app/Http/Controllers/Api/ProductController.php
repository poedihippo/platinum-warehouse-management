<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductStoreRequest;
use App\Http\Requests\Api\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:product_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_create', ['only' => 'store']);
        $this->middleware('permission:product_edit', ['only' => 'update']);
        $this->middleware('permission:product_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('product_access'), 403);
        $products = QueryBuilder::for(Product::with(['productCategory', 'productBrand']))
            ->allowedFilters([
                AllowedFilter::exact('product_category_id'),
                AllowedFilter::exact('product_brand_id'),
                'name'
            ])
            ->allowedSorts(['id', 'product_category_id', 'product_brand_id', 'name', 'created_at'])
            ->paginate($this->per_page);

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('product_access'), 403);
        return new ProductResource($product);
    }

    public function store(ProductStoreRequest $request)
    {
        $product = Product::create($request->validated());

        return new ProductResource($product);
    }

    public function update(Product $product, ProductUpdateRequest $request)
    {
        $product->update($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Product $product)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('product_delete'), 403);
        $product->delete();
        return $this->deletedResponse();
    }
}
