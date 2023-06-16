<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOpnameItemController extends Controller
{
    public function index($stockOpnameId, $stockOpnameDetailId)
    {
        $stockOpnameDetails = DB::table('stock_opname_items')->select('stock_id', 'is_scanned', 'created_at', 'updated_at')->where('stock_opname_detail_id', $stockOpnameDetailId)->get();
        return response()->json($stockOpnameDetails);
    }

    // public function store(StockOpnameDetail $stockOpnameDetail, stockOpnameItemStoreRequest $request)
    // {
    //     $stock = Stock::findOrFail($request->stock_id);

    //     $cek = $stockOpnameDetail->stockOpnameItems()->where('stock_id', $stock->id)->exists();

    //     if ($cek) return response()->json(['message' => 'The product has been scanned'], 400);

    //     $salesOrderItem = $stockOpnameDetail->stockOpnameItems()->create([
    //         'stock_id' => $stock->id
    //     ]);

    //     return new SalesOrderItemResource($salesOrderItem);
    // }

    public function destroy(StockOpnameDetail $stockOpnameDetail, Request $request)
    {
        $request->validate([
            'stock_id' => 'required|exists:stocks,id'
        ]);

        DB::beginTransaction();
        try {
            $stockOpnameDetail->stockOpnameItems()->where('stock_id', $request->stock_id)->delete();
            // SalesOrderService::countFulfilledQty($stockOpnameDetail);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }

        return $this->deletedResponse();
    }
}
