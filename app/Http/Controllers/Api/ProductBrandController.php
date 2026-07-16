<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductBrandStoreRequest;
use App\Http\Requests\Api\ProductBrandUpdateRequest;
use App\Http\Resources\ProductBrandResource;
use App\Models\ProductBrand;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\QueryBuilder;

class ProductBrandController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:product_brand_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_brand_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:product_brand_create', ['only' => 'store']);
        $this->middleware('permission:product_brand_edit', ['only' => 'update']);
        $this->middleware('permission:product_brand_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('product_brand_access'), 403);
        $productCategories = QueryBuilder::for(ProductBrand::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->paginate($this->per_page);

        return ProductBrandResource::collection($productCategories);
    }

    public function show(ProductBrand $productBrand)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('product_brand_access'), 403);
        return new ProductBrandResource($productBrand);
    }

    public function store(ProductBrandStoreRequest $request)
    {
        $productBrand = ProductBrand::create($request->validated());

        return new ProductBrandResource($productBrand);
    }

    public function update(ProductBrand $productBrand, ProductBrandUpdateRequest $request)
    {
        $data = collect($request->validated())->except('logo')->all();

        if ($request->hasFile('logo')) {
            // Replace the existing logo on S3 (delete old, upload new).
            if ($productBrand->logo_path) {
                Storage::disk('s3')->delete($productBrand->logo_path);
            }
            $data['logo_path'] = $this->storeLogo($request->file('logo'), $productBrand->id);
        }

        $productBrand->update($data);

        return (new ProductBrandResource($productBrand))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ProductBrand $productBrand)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('product_brand_delete'), 403);
        $productBrand->delete();
        return $this->deletedResponse();
    }

    private function storeLogo(UploadedFile $file, int $productBrandId): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));

        return $file->storeAs("brands/{$productBrandId}", "logo.{$ext}", 's3');
    }
}
