<?php

namespace App\Interfaces\Repositories\Payment;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;
}
