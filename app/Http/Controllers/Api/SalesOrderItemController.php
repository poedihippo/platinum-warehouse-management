<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SalesOrderItemStoreRequest;
use App\Http\Resources\SalesOrderItemResource;
use App\Models\SalesOrderDetail;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use Illuminate\Http\Request;

class SalesOrderItemController extends Controller
{
    public function store(SalesOrderDetail $salesOrderDetail, SalesOrderItemStoreRequest $request)
    {
        $stock = Stock::FindOrFail($request->stock_id);
        $salesOrderItem = $salesOrderDetail->salesOrderItems()->create([
            'stock_id' => $stock->id
        ]);

        return new SalesOrderItemResource($salesOrderItem);
    }
}
