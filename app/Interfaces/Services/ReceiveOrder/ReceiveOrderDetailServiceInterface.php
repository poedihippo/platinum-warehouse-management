<?php

namespace App\Interfaces\Services\ReceiveOrder;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\ReceiveOrderDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReceiveOrderDetailServiceInterface extends BaseServiceInterface
{
    public function paginateForReceiveOrder(mixed $receiveOrderId, int $perPage): LengthAwarePaginator;

    public function findDetail(mixed $receiveOrderId, mixed $receiveOrderDetailId): ReceiveOrderDetail;

    public function createDetail(mixed $receiveOrderId, mixed $data): ReceiveOrderDetail;

    public function findForVerify(mixed $receiveOrderId, mixed $receiveOrderDetailId): ReceiveOrderDetail;

    public function verify(ReceiveOrderDetail $receiveOrderDetail, mixed $isVerified): ReceiveOrderDetail;

    public function ensureDeletable(mixed $receiveOrderId, ReceiveOrderDetail $receiveOrderDetail): void;

    public function deleteDetail(ReceiveOrderDetail $receiveOrderDetail): void;
}
