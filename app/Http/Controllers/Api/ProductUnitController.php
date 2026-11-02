<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ImportByFileRequest;
use App\Http\Requests\Api\ProductUnit\ChangeProductRequest;
use App\Http\Requests\Api\ProductUnit\RelationRequest;
use App\Http\Requests\Api\ProductUnit\StoreRequest;
use App\Http\Requests\Api\ProductUnit\UpdateRelationRequest;
use App\Http\Requests\Api\ProductUnit\UpdateRequest;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\ProductUnit\ProductUnitResource;
use App\Http\Resources\SalesOrder\SalesOrderDetailResource;
use App\Interfaces\Services\ProductUnit\ProductUnitServiceInterface;
use App\Models\ProductUnit;
use Illuminate\Http\Response;

class ProductUnitController extends BaseController
{
    public function __construct(private ProductUnitServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:product_unit_access', ['only' => ['index', 'show']]);
        // $this->middleware('permission:product_unit_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_unit_create', ['only' => 'store']);
        $this->middleware('permission:product_unit_edit', ['only' => 'update']);
        $this->middleware('permission:product_unit_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $productUnits = $this->service->paginateIndex($this->per_page);

        return ProductUnitResource::collection($productUnits);
    }

    public function show($id)
    {
        $productUnit = $this->service->findForShow($id);

        return new ProductUnitResource($productUnit);
    }

    public function store(StoreRequest $request)
    {
        $this->service->create($request->validated());

        return $this->createdResponse();
    }

    public function update(ProductUnit $productUnit, UpdateRequest $request)
    {
        $this->service->updateProductUnit($productUnit, $request->validated());

        return $this->updatedResponse();
    }

    public function destroy(ProductUnit $productUnit)
    {
        $this->service->delete($productUnit);

        return $this->deletedResponse();
    }

    public function createProductUnitRelations(RelationRequest $request, ProductUnit $productUnit)
    {
        $this->service->createRelations($productUnit, $request->validated(), $request->related_product_units);

        return $this->createdResponse();
    }

    public function updateProductUnitRelations(UpdateRelationRequest $request, ProductUnit $productUnit, int $id)
    {
        $this->service->updateRelations($productUnit, $id, $request->validated(), $request->related_product_units);

        return $this->updatedResponse();
    }

    public function changeProduct(ProductUnit $productUnit, ChangeProductRequest $request)
    {
        $this->service->update($productUnit, $request->validated());

        return (new ProductUnitResource($productUnit))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function userPrice(ProductUnit $productUnit, int $userId)
    {
        $datas = $this->service->paginateUserPrices($productUnit, $userId, $this->per_page);

        return SalesOrderDetailResource::collection($datas);
    }

    public function sampleImport()
    {
        return response()->download(public_path('product_units.xlsx'));
    }

    public function import(ImportByFileRequest $request)
    {
        $this->service->import($request->file);

        return $this->createdResponse('Data imported successfully', 200);
    }

    public function showProductUnitByStock(string $stockId)
    {
        $productUnit = $this->service->findByStock($stockId);

        if (! $productUnit) {
            return response()->json(['message' => 'Product tidak ditemukan.'], 400);
        }

        return new DefaultResource($productUnit);
    }
}
