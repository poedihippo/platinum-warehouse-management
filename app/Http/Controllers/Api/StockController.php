<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockRecordRequest;
use App\Http\Resources\StockProductUnitResource;
use App\Http\Resources\Stocks\BaseStockResource;
use App\Http\Resources\Stocks\StockProductUnitResource as StocksStockProductUnitResource;
use App\Models\ReceiveOrderDetail;
use App\Models\Stock;
use App\Models\StockProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->tokenCan('stock_access'), 403);
        $stockProductUnits = QueryBuilder::for(StockProductUnit::with(['warehouse', 'productUnit'])->withCount(['stocks' => fn ($q) => $q->whereAvailableStock()->whereNull('description')]))
            ->allowedFilters([
                'id', 'warehouse_id',
                AllowedFilter::scope('product_unit'),
                AllowedFilter::scope('product_brand_id', 'whereProductBrandId'),
                AllowedFilter::scope('product_category_id', 'whereProductCategoryId'),
            ])
            ->allowedSorts(['id', 'qty', 'product_unit_id', 'warehouse_id', 'created_at'])
            ->paginate();

        return StockProductUnitResource::collection($stockProductUnits);
    }

    public function details()
    {
        abort_if(!auth()->user()->tokenCan('stock_access'), 403);

        $filter = request()->filter;

        if (isset($filter) && isset($filter['parent_id']) && $filter['parent_id'] != '') {
            $query = Stock::whereNotNull('parent_id');
        } else {
            $query = Stock::withCount('childs')->whereNull('parent_id');
        }

        $stocks = QueryBuilder::for($query)
            ->allowedFilters([
                'id', 'parent_id', 'stock_product_unit_id', 'warehouse_id', 'receive_order_id', 'receive_order_detail_id',
                AllowedFilter::scope('startDate'),
                AllowedFilter::scope('endDate'),
            ])
            ->allowedSorts(['scanned_count', 'scanned_datetime', 'warehouse_id', 'created_at'])
            // ->allowedIncludes(['productUnit', 'warehouse', 'receiveOrderDetail'])
            ->paginate();

        return BaseStockResource::collection($stocks);
    }

    public function show(Stock $stock)
    {
        abort_if(!auth()->user()->tokenCan('stock_access'), 403);
        return new StocksStockProductUnitResource($stock->load(['stockProductUnit' => fn ($q) => $q->withCount(['stocks' => fn ($q) => $q->whereAvailableStock()->whereNull('description')]), 'receiveOrderDetail']));
    }

    public function store(Request $request)
    {
        abort_if(!auth()->user()->tokenCan('stock_create'), 403);
        dd($request->all());
    }

    // public function update(Stock $stock, StockUpdateRequest $request)
    // {
    //     dump($request->all());
    //     dd($request->validated());
    //     $stock->update($request->validated());

    //     return (new BaseStockResource($stock))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    // }

    // public function destroy(Stock $stock)
    // {
    //     // abort_if(!auth()->user()->tokenCan('receive_order_delete'), 403);
    //     $stock->delete();
    //     return $this->deletedResponse();
    // }

    public function grouping(Request $request)
    {
        abort_if(!auth()->user()->tokenCan('stock_grouping'), 403);

        $request->validate([
            'total_group' => 'required|integer|gt:0',
            'qty' => 'required|integer|gt:0',
            // 'warehouse_id' => 'required|exists:warehouses,id',
            'stock_product_unit_id' => 'required_without:receive_order_detail_id|missing_with:receive_order_detail_id|exists:stock_product_units,id',
            'receive_order_detail_id' => 'required_without:stock_product_unit_id|missing_with:stock_product_unit_id|exists:receive_order_details,id',
        ]);

        $totalQtyGrouping = $request->total_group * $request->qty;
        $receiveOrderDetailId = $request->receive_order_detail_id ?? null;

        // grouping dari page stock
        if ($request->stock_product_unit_id) {
            $stockProductUnit = StockProductUnit::findOrFail($request->stock_product_unit_id);
            $totalStock = $stockProductUnit->stocks()->whereNull('parent_id')->doesntHave('childs')->count() ?? 0;

            if ($totalStock == 0) {
                return response()->json(['message' => 'Not enough stock'], 400);
            }

            if ($totalQtyGrouping == 0 || ($totalQtyGrouping > $totalStock)) {
                return response()->json(['message' => 'Total amount of grouping stock exceeds the total stock'], 400);
            }

            $totalGroupStock = $stockProductUnit->stocks()->whereNull('parent_id')->has('childs')->count() ?? 0;

            $productUnit = $stockProductUnit->productUnit;
        } elseif ($request->receive_order_detail_id) {
            //grouping dari page RO
            $receiveOrderDetail = ReceiveOrderDetail::findOrFail($request->receive_order_detail_id);
            $totalStock = $receiveOrderDetail->stocks()->whereNull('parent_id')->doesntHave('childs')->count() ?? 0;

            if ($totalStock == 0) {
                return response()->json(['message' => 'Not enough stock'], 400);
            }

            if ($totalQtyGrouping == 0 || ($totalQtyGrouping > $totalStock)) {
                return response()->json(['message' => 'Total amount of grouping stock exceeds the total stock'], 400);
            }

            $totalGroupStock = $receiveOrderDetail->stocks()->whereNull('parent_id')->has('childs')->count() ?? 0;

            $productUnit = $receiveOrderDetail->productUnit;
            $stockProductUnit = StockProductUnit::where('warehouse_id', $receiveOrderDetail->receiveOrder->warehouse_id)
                ->where('product_unit_id', $receiveOrderDetail->product_unit_id)
                ->first();
        }

        // 1. hitung total stock dari product unit tsb yang parent_id nya null (stock tanpa gruping)
        // 2. qty grouping harus lebih kecil dari total stock
        // 3.


        for ($i = 0; $i < $request->total_group; $i++) {
            $totalGroupStock++;
            $groupStock = Stock::create([
                'stock_product_unit_id' => $stockProductUnit->id,
                // 'product_unit_id' => $productUnit->id,
                // 'warehouse_id' => $warehouseId,
                'receive_order_detail_id' => $receiveOrderDetailId,
                'description' => 'Group ' . $totalGroupStock . ' - ' . $productUnit->code,
            ]);

            $data = QrCode::size(350)
                ->format('png')
                // ->merge(public_path('images/logo-platinum.png'), absolute: true)
                ->generate($groupStock->id);

            $folder = 'qrcode/';
            $fileName = 'group/' . $groupStock->id . '.png';
            $fullPath = $folder .  $fileName;
            Storage::put($fullPath, $data);

            $groupStock->update(['qr_code' => $fullPath]);

            // $stocks = Stock::where('product_unit_id', $productUnit->id)->where('warehouse_id', $warehouseId)->whereNull('parent_id')->where('id', '<>', $groupStock->id)->doesntHave('childs');

            if ($request->stock_product_unit_id) {
                $stocks = $stockProductUnit->stocks()->whereNull('parent_id')->where('id', '<>', $groupStock->id)->doesntHave('childs');
            } else {
                $stocks = $receiveOrderDetail->stocks()->whereNull('parent_id')->where('id', '<>', $groupStock->id)->doesntHave('childs');
            }

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

    public function ungrouping(Stock $stock)
    {
        abort_if(!auth()->user()->tokenCan('stock_grouping'), 403);

        if ($stock->childs->isEmpty()) return response()->json(['message' => 'Stock is not group / have not childs'], 400);

        DB::beginTransaction();
        try {
            $stock->delete();
            Stock::where('parent_id', $stock->id)->update(['parent_id' => null]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 400);
        }

        return response()->json(['message' => 'success']);
    }

    public function printAll(Request $request)
    {
        abort_if(!auth()->user()->tokenCan('stock_print'), 403);

        $filter = $request->filter;
        $query = Stock::select('id', 'parent_id', 'qr_code', 'description');

        if (isset($filter) && isset($filter['stock_product_unit_id']) && $filter['stock_product_unit_id'] != '') {
            $query->where('stock_product_unit_id', $filter['stock_product_unit_id']);
        }

        if (isset($filter) && isset($filter['receive_order_detail_id']) && $filter['receive_order_detail_id'] != '') {
            $query->where('receive_order_detail_id', $filter['receive_order_detail_id']);
        }

        if (isset($filter) && isset($filter['parent_id']) && $filter['parent_id'] != '') {
            if ($request->print_with_parent == 1) {
                $query->where('id', $filter['parent_id'])->orWhere('parent_id', $filter['parent_id'])->orderBy('parent_id');
            } else {
                $query->where('parent_id', $filter['parent_id']);
            }
        } else {
            $query->whereNull('parent_id');
        }

        $stocks = QueryBuilder::for($query)
            ->allowedFilters(['id', 'receive_order_detail_id', 'stock_product_unit_id', 'parent_id', 'product_unit_id', 'warehouse_id'])
            ->allowedSorts(['product_unit_id', 'warehouse_id', 'created_at'])
            ->get();

        return response()->json($stocks);
    }

    public function record(StockRecordRequest $request)
    {
        $stockIds = $request->stock_ids;
        $stockIds = is_array($stockIds) && count($stockIds) > 0 ? $stockIds : [];
        $columnName = '';
        $value = '';
        if ($stockProductUnitId = $request->stock_product_unit_id) {
            $columnName = 'stock_product_unit_id';
            $value = $stockProductUnitId;
        } elseif ($receiveOrderDetailId = $request->receive_order_detail_id) {
            $columnName = 'receive_order_detail_id';
            $value = $receiveOrderDetailId;
        }

        if ($columnName != '' && $value != '') {
            DB::beginTransaction();
            try {
                $time = now();
                if (count($stockIds) > 0) {
                    foreach ($stockIds as $id) {
                        $stock = Stock::where($columnName, $value)->where('id', $id)->first();
                        if ($stock) {
                            if ($stock->childs->count() > 0) {
                                // as parent & has childs
                                $stock->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                                $stock->childs->each->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                            } else {
                                $stock->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                            }
                        }
                    }
                } elseif ($request->is_print_all) {
                    DB::table('stocks')->where($columnName, $value)->increment('scanned_count', 1, ['scanned_datetime' => $time]);
                }
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json(['message' => $th->getMessage()], 400);
            }

            return response()->json(['message' => 'Data updated successfully']);
        }

        return response()->json(['message' => 'No data updated']);
    }
}
