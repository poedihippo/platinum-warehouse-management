<?php

namespace App\Interfaces\Repositories\VoucherGenerateBatch;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VoucherGenerateBatchRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;
}
