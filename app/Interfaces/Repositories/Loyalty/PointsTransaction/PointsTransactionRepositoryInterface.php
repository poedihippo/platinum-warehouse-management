<?php

namespace App\Interfaces\Repositories\Loyalty\PointsTransaction;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PointsTransactionRepositoryInterface extends BaseRepositoryInterface
{
    public function balanceFor(mixed $userId): int;

    public function paginateForUser(mixed $userId, int $perPage): LengthAwarePaginator;
}
