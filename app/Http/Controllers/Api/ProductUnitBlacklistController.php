<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProductUnitBlacklist\ProductUnitBlacklistResource;
use App\Interfaces\Services\ProductUnitBlacklist\ProductUnitBlacklistServiceInterface;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;

class ProductUnitBlacklistController extends BaseController
{
    public function __construct(private ProductUnitBlacklistServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:product_unit_blacklist_access', ['only' => 'index']);
        $this->middleware('permission:product_unit_blacklist_read', ['only' => 'index']);
        $this->middleware('permission:product_unit_blacklist_create', ['only' => 'store']);
        $this->middleware('permission:product_unit_blacklist_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $productUnitBlacklists = $this->service->findAllPaginate(
            $this->per_page,
            fn ($query) => $query->with('productUnit'),
            [
                AllowedFilter::exact('product_unit_id'),
            ],
            [],
            ['product_unit_id'],
        );

        return ProductUnitBlacklistResource::collection($productUnitBlacklists);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_unit_ids' => 'required|array',
            'product_unit_id.*' => 'exists:product_units,id',
        ]);

        $dataInserted = $this->service->blacklist($request->product_unit_ids);

        return response()->json(['message' => $dataInserted.' inserted successfully']);
    }

    public function destroy($productUnitId)
    {
        abort_if(! auth('sanctum')->user()->tokenCan('product_unit_blacklist_delete'), 403);

        $this->service->deleteByProductUnitId($productUnitId);

        return $this->deletedResponse();
    }
}
