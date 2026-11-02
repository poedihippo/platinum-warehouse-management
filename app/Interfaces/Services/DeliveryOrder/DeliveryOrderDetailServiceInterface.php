<?php

namespace App\Interfaces\Services\DeliveryOrder;

use App\Models\DeliveryOrderDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeliveryOrderDetailServiceInterface
{
    public function paginate(int $deliveryOrderId, int $perPage): LengthAwarePaginator;

    public function find(int $deliveryOrderId, int $deliveryOrderDetailId): DeliveryOrderDetail;

    public function paginateItems(int $deliveryOrderDetailId, int $perPage): LengthAwarePaginator;

    public function updateQty(int $deliveryOrderId, int $deliveryOrderDetailId, mixed $qty): DeliveryOrderDetail;

    public function deleteDetail(int $deliveryOrderId, int $deliveryOrderDetailId): void;

    public function resetVerifiedStock(int $deliveryOrderId, int $deliveryOrderDetailId): string;
}
