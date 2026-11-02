<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SalesOrder\ItemStoreRequest;
use App\Http\Resources\SalesOrder\SalesOrderItemResource;
use App\Interfaces\Services\SalesOrder\SalesOrderServiceInterface;
use App\Models\SalesOrderDetail;
use Illuminate\Http\Request;

class SalesOrderItemController extends BaseController
{
    public function __construct(private SalesOrderServiceInterface $service)
    {
        parent::__construct();
    }

    public function index(SalesOrderDetail $salesOrderDetail)
    {
        return response()->json($this->service->scannedStockIds($salesOrderDetail));
    }

    public function store(SalesOrderDetail $salesOrderDetail, ItemStoreRequest $request)
    {
        return new SalesOrderItemResource($this->service->scanItem($salesOrderDetail, $request->stock_id));
    }

    public function destroy(SalesOrderDetail $salesOrderDetail, Request $request)
    {
        $request->validate([
            'stock_id' => 'required|exists:stocks,id',
        ]);

        $this->service->unscanItem($salesOrderDetail, $request->stock_id);

        return $this->deletedResponse();
    }
}
