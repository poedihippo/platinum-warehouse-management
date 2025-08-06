<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultResource;
use App\Models\TemporaryStock;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class TemporaryStockController extends Controller
{
    public function index()
    {
        $products = QueryBuilder::for(
            TemporaryStock::with(['stock' => fn($q) => $q->select('id', 'stock_product_unit_id')->with('stockProductUnit', fn($q) => $q->select('id', 'product_unit_id')->with('productUnit', fn($q) => $q->select('id', 'name', 'code', 'price')))])
        )
            ->allowedFilters([
                'id'
            ])
            ->allowedSorts(['id', 'created_at'])
            ->paginate($this->per_page);

        return DefaultResource::collection($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id' => ['required', 'exists:stocks,id']
        ]);

        TemporaryStock::create(['id' => $request->id, 'created_by_id' => auth()->id()]);

        return response()->json([
            'message' => $request->id . ' stock scanned successfully',
        ]);
    }
}
