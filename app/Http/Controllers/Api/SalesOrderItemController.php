<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SalesOrderItemStoreRequest;
use App\Http\Resources\SalesOrderItemResource;
use App\Models\SalesOrderDetail;
use App\Models\Stock;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderItemController extends Controller
{
    public function index(SalesOrderDetail $salesOrderDetail)
    {
        // $salesOrderItems = SalesOrderItem::select(stock_id','created_at')->where('sales_order_detail_id', $salesOrderDetail->id)->get();
        $salesOrderItems = DB::table('sales_order_items')->select('stock_id', 'created_at')->where('sales_order_detail_id', $salesOrderDetail->id)->get();
        return response()->json($salesOrderItems);
    }

    public function store(SalesOrderDetail $salesOrderDetail, SalesOrderItemStoreRequest $request)
    {
        $stock = Stock::findOrFail($request->stock_id);

        $cek = $salesOrderDetail->salesOrderItems()->where('stock_id', $stock->id)->exists();

        if ($cek) return response()->json(['message' => 'Product sudah di scan'], 400);

        $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
            'stock_id' => $stock->id
        ]);

        return new SalesOrderItemResource($salesOrderItem);
    }

    public function destroy(SalesOrderDetail $salesOrderDetail, Request $request)
    {
        $request->validate([
            'stock_id' => 'required|exists:stocks,id'
        ]);

        DB::beginTransaction();
        try {
            $salesOrderDetail->salesOrderItems()->where('stock_id', $request->stock_id)->delete();
            SalesOrderService::countFulfilledQty($salesOrderDetail);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }

        return $this->deletedResponse();
    }
}
