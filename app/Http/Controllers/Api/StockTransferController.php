<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TransferStockRequest;
// use App\Http\Resources\StockTransferResource;
use App\Models\Stock;
use App\Models\StockProductUnit;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission:stock_edit', ['only' => ['transfer']]);
    }

    public function transfer(TransferStockRequest $request)
    {
        if ($request->has('stock_ids')) {
            return $this->transferQr($request);
        }

        return $this->transferNonQr($request);
    }

    private function transferQr(TransferStockRequest $request)
    {
        $stocks = Stock::whereIn('id', $request->stock_ids)->get();
        $fromSpuId = $request->from_stock_product_unit_id;
        $toSpuId = $request->to_stock_product_unit_id;
        $fromWarehouseId = $request->from_warehouse_id;
        $toWarehouseId = $request->to_warehouse_id;
        $productUnitId = $request->product_unit_id;
        $userId = auth('sanctum')->id();
        $userIp = request()->ip();
        $userAgent = request()->header('user-agent');
        $description = $request->description;

        $fromWarehouseName = \App\Models\Warehouse::where('id', $fromWarehouseId)->value('name');
        $toWarehouseName = \App\Models\Warehouse::where('id', $toWarehouseId)->value('name');

        DB::beginTransaction();
        try {
            $transfers = $stocks->map(function ($stock) use ($fromSpuId, $toSpuId, $fromWarehouseId, $toWarehouseId, $productUnitId, $userId, $userIp, $userAgent, $description, $fromWarehouseName, $toWarehouseName) {
                $childCount = $stock->childs()->count();
                $qty = $childCount > 0 ? $childCount + 1 : 1;

                Stock::where('id', $stock->id)
                    ->orWhere('parent_id', $stock->id)
                    ->update(['stock_product_unit_id' => $toSpuId]);

                $descSuffix = $description ? ' - ' . $description : '';

                $stockTransfer = StockTransfer::create([
                    'stock_id' => $stock->id,
                    'from_stock_product_unit_id' => $fromSpuId,
                    'to_stock_product_unit_id' => $toSpuId,
                    'from_warehouse_id' => $fromWarehouseId,
                    'to_warehouse_id' => $toWarehouseId,
                    'product_unit_id' => $productUnitId,
                    'qty' => $qty,
                    'description' => $description,
                ]);

                $stockTransfer->histories()->create([
                    'user_id' => $userId,
                    'stock_product_unit_id' => $fromSpuId,
                    'value' => $qty,
                    'is_increment' => 0,
                    'description' => 'Transfer stock ke ' . $toWarehouseName . $descSuffix,
                    'ip' => $userIp,
                    'agent' => $userAgent,
                ]);

                $stockTransfer->histories()->create([
                    'user_id' => $userId,
                    'stock_product_unit_id' => $toSpuId,
                    'value' => $qty,
                    'is_increment' => 1,
                    'description' => 'Transfer stock dari ' . $fromWarehouseName . $descSuffix,
                    'ip' => $userIp,
                    'agent' => $userAgent,
                ]);

                return $stockTransfer->load([
                    'stock',
                    'fromStockProductUnit.productUnit',
                    'fromStockProductUnit.warehouse',
                    'toStockProductUnit.productUnit',
                    'toStockProductUnit.warehouse',
                    'user',
                ]);
            });

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }

        return $this->createdResponse("Transfer stock berhasil");
        // return response()->json([
        //     'message' => 'Transfer stock berhasil',
        //     'data' => StockTransferResource::collection($transfers),
        // ]);
    }

    private function transferNonQr(TransferStockRequest $request)
    {
        $fromSpuId = $request->stock_product_unit_id;
        $productUnitId = $request->product_unit_id;
        $qty = $request->qty;
        $userId = auth('sanctum')->id();
        $userIp = request()->ip();
        $userAgent = request()->header('user-agent');

        $fromSpu = StockProductUnit::with('warehouse')->findOrFail($fromSpuId);
        $toSpu = StockProductUnit::with('warehouse')
            ->where('product_unit_id', $productUnitId)
            ->where('warehouse_id', $request->to_warehouse_id)
            ->first();

        DB::beginTransaction();
        try {
            $fromSpu->decrement('qty', $qty);
            $toSpu->increment('qty', $qty);

            $stockTransfer = StockTransfer::create([
                'from_stock_product_unit_id' => $fromSpuId,
                'to_stock_product_unit_id' => $toSpu->id,
                'from_warehouse_id' => $fromSpu->warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'product_unit_id' => $productUnitId,
                'qty' => $qty,
                'description' => $request->description,
            ]);

            $stockTransfer->histories()->create([
                'user_id' => $userId,
                'stock_product_unit_id' => $fromSpuId,
                'value' => $qty,
                'is_increment' => 0,
                'description' => 'Transfer stock ke ' . $toSpu->warehouse->name . ($request->description ? ' - ' . $request->description : ''),
                'ip' => $userIp,
                'agent' => $userAgent,
            ]);

            $stockTransfer->histories()->create([
                'user_id' => $userId,
                'stock_product_unit_id' => $toSpu->id,
                'value' => $qty,
                'is_increment' => 1,
                'description' => 'Transfer stock dari ' . $fromSpu->warehouse->name . ($request->description ? ' - ' . $request->description : ''),
                'ip' => $userIp,
                'agent' => $userAgent,
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }

        return $this->createdResponse("Transfer stock berhasil");
        // return response()->json([
        //     'message' => 'Transfer stock berhasil',
        //     'data' => new StockTransferResource(
        //         $stockTransfer->load(['fromStockProductUnit.productUnit', 'fromStockProductUnit.warehouse', 'toStockProductUnit.productUnit', 'toStockProductUnit.warehouse', 'user'])
        //     ),
        // ]);
    }
}
