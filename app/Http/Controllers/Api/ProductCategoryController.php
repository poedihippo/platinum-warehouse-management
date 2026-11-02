<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductCategory\StoreRequest;
use App\Http\Requests\Api\ProductCategory\UpdateRequest;
use App\Http\Resources\ProductCategory\ProductCategoryResource;
use App\Interfaces\Services\ProductCategory\ProductCategoryServiceInterface;
use App\Models\ProductCategory;
use Illuminate\Http\Response;

class ProductCategoryController extends BaseController
{
    public function __construct(private ProductCategoryServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:product_category_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:product_category_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_category_create', ['only' => 'store']);
        $this->middleware('permission:product_category_edit', ['only' => 'update']);
        $this->middleware('permission:product_category_delete', ['only' => 'destroy']);
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

        return ProductCategoryResource::collection($productCategories);
    }

    public function show(ProductCategory $productCategory)
    {
        return new ProductCategoryResource($productCategory);
    }

    public function store(StoreRequest $request)
    {
        $productCategory = $this->service->create($request->validated());

        return new ProductCategoryResource($productCategory);
    }

    public function update(ProductCategory $productCategory, UpdateRequest $request)
    {
        $this->service->update($productCategory, $request->validated());

        return (new ProductCategoryResource($productCategory))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ProductCategory $productCategory)
    {
        $this->service->delete($productCategory);

        return $this->deletedResponse();
    }
}
