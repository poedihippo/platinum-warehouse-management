<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductBrand\StoreRequest;
use App\Http\Requests\Api\ProductBrand\UpdateRequest;
use App\Http\Resources\ProductBrand\ProductBrandResource;
use App\Interfaces\Services\ProductBrand\ProductBrandServiceInterface;
use App\Models\ProductBrand;
use Illuminate\Http\Response;

class ProductBrandController extends BaseController
{
    public function __construct(private ProductBrandServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:product_brand_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:product_brand_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_brand_create', ['only' => 'store']);
        $this->middleware('permission:product_brand_edit', ['only' => 'update']);
        $this->middleware('permission:product_brand_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $productCategories = $this->service->findAllPaginate(
            $this->per_page,
            null,
            ['name'],
            [],
            ['id', 'name', 'created_at'],
        );

        return ProductBrandResource::collection($productCategories);
    }

    public function show(ProductBrand $productBrand)
    {
        return new ProductBrandResource($productBrand);
    }

    public function store(StoreRequest $request)
    {
        $productBrand = $this->service->create($request->validated());

        return new ProductBrandResource($productBrand);
    }

    public function update(ProductBrand $productBrand, UpdateRequest $request)
    {
        $this->service->updateWithLogo(
            $productBrand,
            $request->validated(),
            $request->hasFile('logo') ? $request->file('logo') : null,
        );

        return (new ProductBrandResource($productBrand))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ProductBrand $productBrand)
    {
        $this->service->delete($productBrand);

        return $this->deletedResponse();
    }
}
