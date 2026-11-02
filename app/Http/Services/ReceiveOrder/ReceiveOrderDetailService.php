<?php

namespace App\Http\Services\ReceiveOrder;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\ReceiveOrder\ReceiveOrderDetailRepositoryInterface;
use App\Interfaces\Repositories\ReceiveOrder\ReceiveOrderRepositoryInterface;
use App\Interfaces\Services\ReceiveOrder\ReceiveOrderDetailServiceInterface;
use App\Models\ReceiveOrderDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReceiveOrderDetailService extends BaseService implements ReceiveOrderDetailServiceInterface
{
    public function __construct(
        protected ReceiveOrderDetailRepositoryInterface $repository,
        protected ReceiveOrderRepositoryInterface $receiveOrderRepository,
    ) {
        parent::__construct($repository);
    }

    public function paginateForReceiveOrder(mixed $receiveOrderId, int $perPage): LengthAwarePaginator
    {
        $receiveOrder = $this->receiveOrderRepository->findTenanted($receiveOrderId, ['id']);

        return $this->repository->paginateForReceiveOrder($receiveOrder->id, $perPage);
    }

    public function findDetail(mixed $receiveOrderId, mixed $receiveOrderDetailId): ReceiveOrderDetail
    {
        $receiveOrder = $this->receiveOrderRepository->findTenanted($receiveOrderId, ['id']);

        return $receiveOrder->details()->where('id', $receiveOrderDetailId)->firstOrFail();
    }

    public function createDetail(mixed $receiveOrderId, mixed $data): ReceiveOrderDetail
    {
        $receiveOrder = $this->receiveOrderRepository->findTenanted($receiveOrderId, ['id']);

        return $receiveOrder->details()->create($data);
    }

    /**
     * Known issue kept as is: the receive order is loaded with id only, so the
     * is_done guard never fires.
     */
    public function findForVerify(mixed $receiveOrderId, mixed $receiveOrderDetailId): ReceiveOrderDetail
    {
        $receiveOrder = $this->receiveOrderRepository->findTenanted($receiveOrderId, ['id']);
        if ($receiveOrder->is_done) {
            throw new ApiResponseException('Delivery order sudah diverifikasi. Tidak dapat mengubah detail', 400);
        }

        return $receiveOrder->details()->where('id', $receiveOrderDetailId)->firstOrFail();
    }

    public function verify(ReceiveOrderDetail $receiveOrderDetail, mixed $isVerified): ReceiveOrderDetail
    {
        $receiveOrderDetail->is_verified = boolval($isVerified);
        if ($receiveOrderDetail->isDirty('is_verified') === false) {
            throw new ApiResponseException('Tidak dapat memperbarui dengan status yang sama', 400);
        }

        $receiveOrderDetail->save();

        return $receiveOrderDetail;
    }

    public function ensureDeletable(mixed $receiveOrderId, ReceiveOrderDetail $receiveOrderDetail): void
    {
        $this->receiveOrderRepository->findTenanted($receiveOrderId, ['id']);
        if ($receiveOrderDetail->is_verified === true) {
            throw new ApiResponseException('Data harus tidak diverifikasi', 200);
        }
    }

    public function deleteDetail(ReceiveOrderDetail $receiveOrderDetail): void
    {
        DB::beginTransaction();
        try {
            $receiveOrderDetail->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }
    }
}
