<?php

namespace App\Http\Controllers\Api;

use App\Interfaces\Services\StockOpname\StockOpnameServiceInterface;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use Illuminate\Http\Request;

class StockOpnameItemController extends BaseController
{
    public function __construct(private StockOpnameServiceInterface $service)
    {
        parent::__construct();
    }

    public function index($stockOpnameId, $stockOpnameDetailId)
    {
        return response()->json($this->service->items($stockOpnameDetailId));
    }

    public function destroy(StockOpname $stockOpname, StockOpnameDetail $stockOpnameDetail, Request $request)
    {
        $request->validate([
            'stock_id' => 'required|exists:stocks,id',
        ]);

        $this->service->deleteItem($stockOpnameDetail, $request->stock_id);

        return $this->deletedResponse();
    }
}
