<?php

namespace App\Http\Services\AdjustmentRequest;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\AdjustmentRequest\AdjustmentRequestRepositoryInterface;
use App\Interfaces\Repositories\Stock\StockRepositoryInterface;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Services\AdjustmentRequest\AdjustmentRequestServiceInterface;
use App\Models\AdjustmentRequest;
use Illuminate\Support\Facades\DB;

class AdjustmentRequestService extends BaseService implements AdjustmentRequestServiceInterface
{
    public function __construct(
        protected AdjustmentRequestRepositoryInterface $repository,
        protected StockRepositoryInterface $stockRepository,
        protected StockHistoryRepositoryInterface $stockHistoryRepository,
    ) {
        parent::__construct($repository);
    }

    public function updateUnlessApproved(AdjustmentRequest $adjustmentRequest, array $data): AdjustmentRequest
    {
        if ($adjustmentRequest->is_approved) {
            throw new ApiResponseException('Tidak dapat update data jika sudah di approved', 400);
        }

        return $this->repository->update($adjustmentRequest, $data);
    }

    public function deleteUnlessApproved(AdjustmentRequest $adjustmentRequest): void
    {
        if ($adjustmentRequest->is_approved) {
            throw new ApiResponseException('Tidak dapat menghapus data jika sudah di approved', 400);
        }

        $this->repository->delete($adjustmentRequest);
    }

    /**
     * Approve or reject. Approving applies the movement: QR products get or lose
     * individual stock rows, non-QR products change StockProductUnit.qty, and a
     * stock history row is written. Rejecting only records the decision.
     */
    public function decide(AdjustmentRequest $adjustmentRequest, mixed $reason, mixed $isApproved): AdjustmentRequest
    {
        if (! is_null($adjustmentRequest->is_approved)) {
            throw new ApiResponseException(sprintf('Adjustment request sudah di %s. Tidak dapat di edit!', $adjustmentRequest->is_approved ? 'approve' : 'reject'), 404);
        }

        /** @var \App\Models\StockProductUnit $stockProductUnit */
        $stockProductUnit = $adjustmentRequest->stockProductUnit;
        if (! $stockProductUnit) {
            throw new ApiResponseException('Stock product unit tidak ditemukan', 404);
        }

        DB::beginTransaction();
        try {
            $adjustmentRequest->reason = $reason ?? null;
            $adjustmentRequest->is_approved = $isApproved ?? null;
            $adjustmentRequest->approved_by = auth('sanctum')->id();
            $adjustmentRequest->approved_datetime = now();

            if ($adjustmentRequest->is_approved == true) {
                if ($adjustmentRequest->is_increment == 1) {
                    if ($stockProductUnit->productUnit->is_generate_qr) {
                        $isStock = $stockProductUnit->productUnit->is_auto_stock;

                        for ($i = 0; $i < $adjustmentRequest->value ?? 0; $i++) {
                            $stockProductUnit->stocks()->create([
                                'adjustment_request_id' => $adjustmentRequest->id,
                                'is_stock' => $isStock,
                                'description' => null,
                                'expired_date' => $adjustmentRequest->expired_date,
                            ]);
                        }
                    } else {
                        $stockProductUnit->increment('qty', $adjustmentRequest->value);
                    }

                    $this->stockHistoryRepository->record($adjustmentRequest, [
                        'user_id' => auth('sanctum')->id(),
                        'stock_product_unit_id' => $adjustmentRequest->stock_product_unit_id,
                        'value' => $adjustmentRequest->value ?? 0,
                        'is_increment' => 1,
                        'description' => 'Adjustment request (Penambahan) - '.$adjustmentRequest->description,
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);
                } else {
                    if ($stockProductUnit->productUnit->is_generate_qr) {
                        $adjustmentRequest->stocks?->each->forceDelete();
                        $this->stockRepository->deleteAvailable($stockProductUnit->id, $adjustmentRequest->value);
                    } else {
                        $stockProductUnit->decrement('qty', $adjustmentRequest->value);
                    }

                    $this->stockHistoryRepository->record($adjustmentRequest, [
                        'user_id' => auth('sanctum')->id(),
                        'stock_product_unit_id' => $adjustmentRequest->stock_product_unit_id,
                        'value' => $adjustmentRequest->value ?? 0,
                        'is_increment' => 0,
                        'description' => 'Adjustment request (Pengurangan) - '.$adjustmentRequest->description,
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);
                }
            }

            $adjustmentRequest->save();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw new ApiResponseException($th->getMessage(), 500);
        }

        return $adjustmentRequest;
    }
}
