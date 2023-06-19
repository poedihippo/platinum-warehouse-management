<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockProductUnitResource;
use App\Http\Resources\StockResource;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\StockProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\QueryBuilder\QueryBuilder;

class StockControllerCopy extends Controller
{
    public function index()
    {
        $stockProductUnits = QueryBuilder::for(StockProductUnit::with(['warehouse', 'productUnit'])->withCount('stocks'))
            ->allowedSorts(['id', 'qty', 'product_unit_id', 'warehouse_id', 'created_at'])
            ->paginate();

        return StockProductUnitResource::collection($stockProductUnits);
    }

    public function details()
    {
        // abort_if(!auth()->user()->tokenCan('receive_orders_access'), 403);

        $filter = request()->filter;

        if (isset($filter) && isset($filter['parent_id']) && $filter['parent_id'] != '') {
            $query = Stock::whereNotNull('parent_id');
        } else {
            $query = Stock::withCount('childs')->whereNull('parent_id');
        }

        $stocks = QueryBuilder::for($query)
            ->allowedFilters(['id', 'parent_id', 'stock_product_unit_id', 'warehouse_id', 'receive_order_id', 'receive_order_detail_id'])
            ->allowedSorts(['warehouse_id', 'created_at'])
            // ->allowedIncludes(['productUnit', 'warehouse', 'receiveOrderDetail'])
            ->paginate();

        return StockResource::collection($stocks);
    }

    public function show(Stock $stock)
    {
        // abort_if(!auth()->user()->tokenCan('receive_order_create'), 403);
        return new StockResource($stock->load('details'));
    }

    public function store(Request $request)
    {
        dd($request->all());
    }

    // public function update(Stock $stock, StockUpdateRequest $request)
    // {
    //     dump($request->all());
    //     dd($request->validated());
    //     $stock->update($request->validated());

    //     return (new StockResource($stock))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    // }

    // public function destroy(Stock $stock)
    // {
    //     // abort_if(!auth()->user()->tokenCan('receive_order_delete'), 403);
    //     $stock->delete();
    //     return $this->deletedResponse();
    // }

    public function grouping(ProductUnit $productUnit, Request $request)
    {
        $request->validate([
            'total_group' => 'required|integer|gt:0',
            'qty' => 'required|integer|gt:0',
            // 'warehouse_id' => 'required|exists:warehouses,id',
            'stock_product_unit_id' => 'nullable|exists:stock_product_units,id',
            'receive_order_detail_id' => 'nullable|exists:receive_order_details,id',
        ]);

        $totalQtyGrouping = $request->total_group * $request->qty;
        $receiveOrderDetailId = $request->receive_order_detail_id ?? null;
        $warehouseId = $request->warehouse_id;


        if($request->stock_product_unit_id){

        } elseif($request->receive_order_detail_id) {

        }



        $totalStock = $productUnit->stocks()->where('warehouse_id', $warehouseId)->whereNull('parent_id')->doesntHave('childs');
        if (!is_null($receiveOrderDetailId)) {
            $totalStock->where('receive_order_detail_id', $receiveOrderDetailId);
        }

        // 1. hitung total stock dari product unit tsb yang parent_id nya null (stock tanpa gruping)
        // 2. qty grouping harus lebih kecil dari total stock
        // 3.

        $totalStock = $totalStock->count() ?? 0;
        dd($totalStock);
        if ($totalStock == 0) {
            return response()->json(['message' => 'Not enough stock'], 400);
        }

        if ($totalQtyGrouping == 0 || ($totalQtyGrouping > $totalStock)) {
            return response()->json(['message' => 'Total amount of grouping stock exceeds the total stock'], 400);
        }

        $totalGroupStock = $productUnit->stocks()->where('warehouse_id', $warehouseId)->whereNull('parent_id')->has('childs')->count() ?? 0;

        for ($i = 0; $i < $request->total_group; $i++) {
            $totalGroupStock++;
            $groupStock = Stock::create([
                'product_unit_id' => $productUnit->id,
                'warehouse_id' => $warehouseId,
                'receive_order_detail_id' => $receiveOrderDetailId,
                'description' => 'Group ' . $totalGroupStock . ' - ' . $productUnit->code,
            ]);

            $data = QrCode::size(350)
                ->format('png')
                ->merge(public_path('images/logo-platinum.png'), absolute: true)
                ->generate($groupStock->id);

            $folder = 'qrcode/';
            $fileName = 'group/' . $groupStock->id . '.png';
            $fullPath = $folder .  $fileName;
            Storage::put($fullPath, $data);

            $groupStock->update(['qr_code' => $fullPath]);

            $stocks = Stock::where('product_unit_id', $productUnit->id)->where('warehouse_id', $warehouseId)->whereNull('parent_id')->where('id', '<>', $groupStock->id)->doesntHave('childs');

            if (is_null($groupStock->receive_order_detail_id)) {
                $stocks->orderBy('receive_order_detail_id');
            } else {
                $stocks->where('receive_order_detail_id', $groupStock->receive_order_detail_id);
            }

            $stocks->limit($request->qty)->get()?->each->update([
                'parent_id' => $groupStock->id
            ]);
        }

        return response()->json(['message' => 'Stock group created successfully'], 201);
    }

    public function printAll(Request $request)
    {
        $filter = $request->filter;
        $query = Stock::select('id', 'parent_id', 'qr_code');

        if (isset($filter) && isset($filter['parent_id']) && $filter['parent_id'] != '') {
            $query->where('parent_id', $filter['parent_id']);
        } else {
            $query->whereNull('parent_id');
        }

        $stocks = QueryBuilder::for($query)
            ->allowedFilters(['id', 'receive_order_detail_id', 'parent_id', 'product_unit_id', 'warehouse_id'])
            ->allowedSorts(['product_unit_id', 'warehouse_id', 'created_at'])
            ->get();

        return response()->json($stocks);
    }
}