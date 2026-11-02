<?php

namespace App\Interfaces\Repositories\StockProductUnit;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StockProductUnitRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateStockIndex(int $perPage): LengthAwarePaginator;
}
