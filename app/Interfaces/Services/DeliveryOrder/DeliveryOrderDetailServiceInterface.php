<?php

namespace App\Interfaces\Services\DeliveryOrder;

use App\Models\DeliveryOrderDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeliveryOrderDetailServiceInterface
{
    public function paginate(mixed $deliveryOrderId, int $perPage): LengthAwarePaginator;

    public function find(mixed $deliveryOrderId, mixed $deliveryOrderDetailId): DeliveryOrderDetail;

    public function paginateItems(mixed $deliveryOrderDetailId, int $perPage): LengthAwarePaginator;

    public function updateQty(mixed $deliveryOrderId, mixed $deliveryOrderDetailId, mixed $qty): DeliveryOrderDetail;

    public function deleteDetail(mixed $deliveryOrderId, mixed $deliveryOrderDetailId): void;

    public function resetVerifiedStock(mixed $deliveryOrderId, mixed $deliveryOrderDetailId): string;
}
