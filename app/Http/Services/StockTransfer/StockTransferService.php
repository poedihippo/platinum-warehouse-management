<?php

namespace App\Http\Services\StockTransfer;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Repositories\StockTransfer\StockTransferRepositoryInterface;
use App\Interfaces\Services\StockTransfer\StockTransferServiceInterface;
use App\Models\Stock;
use App\Models\StockProductUnit;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockTransferService extends BaseService implements StockTransferServiceInterface
{
    public function __construct(
        protected StockTransferRepositoryInterface $repository,
        protected StockHistoryRepositoryInterface $stockHistoryRepository,
    ) {
        parent::__construct($repository);
    }

    public function transfer(array $input, bool $isQr): void
    {
        if ($isQr) {
            $this->transferQr($input);

            return;
        }

        $this->transferNonQr($input);
    }

    /**
     * Moves each stock (with its children) to the destination stock product unit.
     * Relies on the ids merged into the request by TransferRequest.
     */
    private function transferQr(array $input): void
    {
        $stocks = Stock::whereIn('id', $input['stock_ids'] ?? null)->get();
        $fromSpuId = $input['from_stock_product_unit_id'] ?? null;
        $toSpuId = $input['to_stock_product_unit_id'] ?? null;
        $fromWarehouseId = $input['from_warehouse_id'] ?? null;
        $toWarehouseId = $input['to_warehouse_id'] ?? null;
        $productUnitId = $input['product_unit_id'] ?? null;
        $userId = auth('sanctum')->id();
        $userIp = request()->ip();
        $userAgent = request()->header('user-agent');
        $description = $input['description'] ?? null;

        $fromWarehouseName = Warehouse::where('id', $fromWarehouseId)->value('name');
        $toWarehouseName = Warehouse::where('id', $toWarehouseId)->value('name');

        DB::beginTransaction();
        try {
            $stocks->map(function ($stock) use ($fromSpuId, $toSpuId, $fromWarehouseId, $toWarehouseId, $productUnitId, $userId, $userIp, $userAgent, $description, $fromWarehouseName, $toWarehouseName) {
                $childCount = $stock->childs()->count();
                $qty = $childCount > 0 ? $childCount : 1;

                if ($childCount > 0) {
                    Stock::where('id', $stock->id)
                        ->orWhere('parent_id', $stock->id)
                        ->update(['stock_product_unit_id' => $toSpuId]);
                } else {
                    Stock::where('id', $stock->id)
                        ->update(['stock_product_unit_id' => $toSpuId]);
                }

                $descSuffix = $description ? ' - '.$description : '';

                $stockTransfer = $this->repository->create([
                    'stock_id' => $stock->id,
                    'from_stock_product_unit_id' => $fromSpuId,
                    'to_stock_product_unit_id' => $toSpuId,
                    'from_warehouse_id' => $fromWarehouseId,
                    'to_warehouse_id' => $toWarehouseId,
                    'product_unit_id' => $productUnitId,
                    'qty' => $qty,
                    'description' => $description,
                ]);

                $this->stockHistoryRepository->record($stockTransfer, [
                    'user_id' => $userId,
                    'stock_product_unit_id' => $fromSpuId,
                    'value' => $qty,
                    'is_increment' => 0,
                    'description' => 'Transfer stock ke '.$toWarehouseName.$descSuffix,
                    'ip' => $userIp,
                    'agent' => $userAgent,
                ]);

                $this->stockHistoryRepository->record($stockTransfer, [
                    'user_id' => $userId,
                    'stock_product_unit_id' => $toSpuId,
                    'value' => $qty,
                    'is_increment' => 1,
                    'description' => 'Transfer stock dari '.$fromWarehouseName.$descSuffix,
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

            throw new ApiResponseException($th->getMessage(), 500);
        }
    }

    /**
     * Known issue kept as is: TransferRequest requires stock_ids, so this path
     * cannot be reached through the endpoint.
     */
    private function transferNonQr(array $input): void
    {
        $fromSpuId = $input['stock_product_unit_id'] ?? null;
        $productUnitId = $input['product_unit_id'] ?? null;
        $qty = $input['qty'] ?? null;
        $userId = auth('sanctum')->id();
        $userIp = request()->ip();
        $userAgent = request()->header('user-agent');

        $fromSpu = StockProductUnit::with('warehouse')->findOrFail($fromSpuId);
        $toSpu = StockProductUnit::with('warehouse')
            ->where('product_unit_id', $productUnitId)
            ->where('warehouse_id', $input['to_warehouse_id'] ?? null)
            ->first();

        DB::beginTransaction();
        try {
            $fromSpu->decrement('qty', $qty);
            $toSpu->increment('qty', $qty);

            $description = $input['description'] ?? null;

            $stockTransfer = $this->repository->create([
                'from_stock_product_unit_id' => $fromSpuId,
                'to_stock_product_unit_id' => $toSpu->id,
                'from_warehouse_id' => $fromSpu->warehouse_id,
                'to_warehouse_id' => $input['to_warehouse_id'] ?? null,
                'product_unit_id' => $productUnitId,
                'qty' => $qty,
                'description' => $description,
            ]);

            $this->stockHistoryRepository->record($stockTransfer, [
                'user_id' => $userId,
                'stock_product_unit_id' => $fromSpuId,
                'value' => $qty,
                'is_increment' => 0,
                'description' => 'Transfer stock ke '.$toSpu->warehouse->name.($description ? ' - '.$description : ''),
                'ip' => $userIp,
                'agent' => $userAgent,
            ]);

            $this->stockHistoryRepository->record($stockTransfer, [
                'user_id' => $userId,
                'stock_product_unit_id' => $toSpu->id,
                'value' => $qty,
                'is_increment' => 1,
                'description' => 'Transfer stock dari '.$fromSpu->warehouse->name.($description ? ' - '.$description : ''),
                'ip' => $userIp,
                'agent' => $userAgent,
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw new ApiResponseException($th->getMessage(), 500);
        }
    }
}
