<?php

namespace App\Interfaces\Services\ReceiveOrder;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\ReceiveOrderDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReceiveOrderDetailServiceInterface extends BaseServiceInterface
{
    public function paginateForReceiveOrder(int $receiveOrderId, int $perPage): LengthAwarePaginator;

    public function findDetail(int $receiveOrderId, mixed $receiveOrderDetailId): ReceiveOrderDetail;

    public function createDetail(int $receiveOrderId, array $data): ReceiveOrderDetail;

    public function findForVerify(int $receiveOrderId, mixed $receiveOrderDetailId): ReceiveOrderDetail;

    public function verify(ReceiveOrderDetail $receiveOrderDetail, mixed $isVerified): ReceiveOrderDetail;

    public function ensureDeletable(int $receiveOrderId, ReceiveOrderDetail $receiveOrderDetail): void;

    public function deleteDetail(ReceiveOrderDetail $receiveOrderDetail): void;
}
