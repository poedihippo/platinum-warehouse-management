<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SalesOrderItemStoreRequest;
use App\Http\Resources\SalesOrderItemResource;
use App\Models\SalesOrderDetail;
use App\Models\Stock;

class SalesOrderItemController extends Controller
{
    public function store(SalesOrderDetail $salesOrderDetail, SalesOrderItemStoreRequest $request)
    {
        $stock = Stock::findOrFail($request->stock_id);

        $cek = $salesOrderDetail->salesOrderItems()->where('stock_id', $stock->id)->exists();

        if ($cek) return response()->json(['message' => 'The product has been scanned'], 400);

        $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
            'stock_id' => $stock->id
        ]);

        return new SalesOrderItemResource($salesOrderItem);
    }
}
