<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ImportByFileRequest;
use App\Http\Requests\Api\Product\StoreRequest;
use App\Http\Requests\Api\Product\UpdateRequest;
use App\Http\Resources\Product\ProductResource;
use App\Interfaces\Services\Product\ProductServiceInterface;
use App\Models\Product;
use Spatie\QueryBuilder\AllowedFilter;

class ProductController extends BaseController
{
    public function __construct(private ProductServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:product_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:product_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_create', ['only' => 'store']);
        $this->middleware('permission:product_edit', ['only' => 'update']);
        $this->middleware('permission:product_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $products = $this->service->findAllPaginate(
            $this->per_page,
            fn ($query) => $query->with([
                'productCategory' => fn ($q) => $q->select('id', 'name'),
                'productBrand' => fn ($q) => $q->select('id', 'name'),
            ]),
            [
                AllowedFilter::exact('product_category_id'),
                AllowedFilter::exact('product_brand_id'),
                AllowedFilter::exact('company'),
                'name',
            ],
            [],
            ['id', 'product_category_id', 'product_brand_id', 'company', 'name', 'created_at'],
        );

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $product->load([
            'productCategory' => fn ($q) => $q->select('id', 'name'),
            'productBrand' => fn ($q) => $q->select('id', 'name'),
        ]);

        return new ProductResource($product);
    }

    public function store(StoreRequest $request)
    {
        $this->service->create($request->validated());

        return $this->createdResponse();
    }

    public function update(Product $product, UpdateRequest $request)
    {
        $this->service->update($product, $request->validated());

        return $this->updatedResponse();
    }

    public function destroy(Product $product)
    {
        $this->service->delete($product);

        return $this->deletedResponse();
    }

    public function sampleImport()
    {
        return response()->download(public_path('products.xlsx'));
    }

    public function import(ImportByFileRequest $request)
    {
        $this->service->import($request->file);

        return $this->createdResponse('Data imported successfully', 200);
    }
}
