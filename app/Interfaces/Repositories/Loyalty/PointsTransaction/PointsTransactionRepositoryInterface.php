<?php

namespace App\Interfaces\Repositories\Loyalty\PointsTransaction;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PointsTransactionRepositoryInterface extends BaseRepositoryInterface
{
    public function balanceFor(string $userId): int;

    public function paginateForUser(string $userId, int $perPage): LengthAwarePaginator;
}
