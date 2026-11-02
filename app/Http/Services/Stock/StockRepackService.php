<?php

namespace App\Http\Services\Stock;

use App\Exceptions\ApiResponseException;
use App\Interfaces\Repositories\AdjustmentRequest\AdjustmentRequestRepositoryInterface;
use App\Interfaces\Repositories\Stock\StockRepositoryInterface;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Repositories\StockProductUnit\StockProductUnitRepositoryInterface;
use App\Interfaces\Services\Stock\StockRepackServiceInterface;
use App\Jobs\GenerateStockQrcode;
use Exception;
use Illuminate\Support\Facades\DB;

class StockRepackService implements StockRepackServiceInterface
{
    public function __construct(
        protected StockRepositoryInterface $repository,
        protected StockProductUnitRepositoryInterface $stockProductUnitRepository,
        protected AdjustmentRequestRepositoryInterface $adjustmentRequestRepository,
        protected StockHistoryRepositoryInterface $stockHistoryRepository,
    ) {
    }

    /**
     * Removes one stock and adds qty to another stock product unit through an
     * auto-approved adjustment request. Both history rows may be backdated.
     */
    public function repack(string $id, mixed $qty, mixed $targetStockProductUnitId, mixed $createdAt): string
    {
        $stock = $this->repository->findTenanted($id, ['id', 'parent_id', 'stock_product_unit_id']);
        if ($stock->childs?->count() > 0) {
            throw new ApiResponseException('Tidak dapat me-repack stock parent', 400);
        }

        $stock->load(['salesOrderItems' => fn ($q) => $q->whereNotReturned()->select('id', 'stock_id')]);
        if ($stock->salesOrderItems?->count() > 0) {
            throw new ApiResponseException('Tidak dapat me-repack stock. Stock sudah masuk di Sales Order', 400);
        }

        $stockProductUnit = $this->stockProductUnitRepository->findByIdOrFail($targetStockProductUnitId);
        $userId = auth('sanctum')->user()->id;
        $userIp = request()->ip();
        $userAgent = request()->header('user-agent');
        $createdAt = $createdAt ? date('Y-m-d H:i:s', strtotime($createdAt)) : now();

        DB::beginTransaction();
        try {
            $stock->delete();
            $this->stockHistoryRepository->record($stock->stockProductUnit, [
                'user_id' => $userId,
                'stock_product_unit_id' => $stock->stockProductUnit->id,
                'value' => 1,
                'is_increment' => 0,
                'description' => sprintf('Delete stock untuk repack - %s ke %s(sebanyak %d)', $stock->stockProductUnit->productUnit->name ?? '', $stockProductUnit->productUnit->name ?? '', $qty),
                'ip' => $userIp,
                'agent' => $userAgent,
                'created_at' => $createdAt,
            ]);

            $adjustmentRequest = $this->adjustmentRequestRepository->create([
                'user_id' => $userId,
                'approved_by' => $userId,
                'stock_product_unit_id' => $stockProductUnit->id,
                'value' => $qty,
                'is_increment' => 1,
                'is_approved' => 1,
                'description' => sprintf('Add stock from repack - %s(sebanyak %d)', $stock->stockProductUnit->productUnit?->name ?? '', $qty),
            ]);

            if ($stockProductUnit->productUnit->is_generate_qr) {
                GenerateStockQrcode::dispatchSync($stockProductUnit, $qty);
            } else {
                $stockProductUnit->increment('qty', $qty);
            }

            $this->stockHistoryRepository->record($adjustmentRequest, [
                'user_id' => $userId,
                'stock_product_unit_id' => $stockProductUnit->id,
                'value' => $qty,
                'is_increment' => 1,
                'description' => $adjustmentRequest->description,
                'ip' => $userIp,
                'agent' => $userAgent,
                'created_at' => $createdAt,
            ]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw new ApiResponseException($e->getMessage(), 500);
        }

        return sprintf('Berhasil me-repack %s ke %s sebanyak %d', $stock->stockProductUnit->productUnit?->name ?? '', $stockProductUnit->productUnit?->name ?? '', $qty);
    }
}
