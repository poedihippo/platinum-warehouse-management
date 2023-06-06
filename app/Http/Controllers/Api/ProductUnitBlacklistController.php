<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductUnitBlacklistResource;
use App\Models\ProductUnitBlacklist;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class ProductUnitBlacklistController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('product_unit_blacklists_access'), 403);

        $productUnitBlacklists = QueryBuilder::for(ProductUnitBlacklist::with('productUnit'))
            ->allowedFilters('product_unit_id')
            ->allowedSorts('product_unit_id')
            ->paginate();

        return ProductUnitBlacklistResource::collection($productUnitBlacklists);
    }

    public function store(Request $request)
    {
        abort_if(!auth()->user()->tokenCan('product_unit_blacklists_create'), 403);

        $request->validate([
            'product_unit_ids' => 'required|array',
            'product_unit_id.*' => 'exists:product_units,id'
        ]);

        $dataInserted = 0;
        foreach ($request->product_unit_ids as $id) {
            if (ProductUnitBlacklist::where('product_unit_id', $id)->doesntExist()) {
                ProductUnitBlacklist::create(['product_unit_id' => $id]);
                $dataInserted++;
            }
        }

        return response()->json(['message' => $dataInserted . ' inserted successfully']);
    }

    public function destroy($productUnitId)
    {
        abort_if(!auth()->user()->tokenCan('product_unit_blacklists_delete'), 403);

        ProductUnitBlacklist::where('product_unit_id', $productUnitId)->delete();
        return $this->deletedResponse();
    }
}
