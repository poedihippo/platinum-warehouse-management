<?php

namespace App\Interfaces\Repositories\ReceiveOrder;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReceiveOrderDetailRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateForReceiveOrder(mixed $receiveOrderId, int $perPage): LengthAwarePaginator;
}
