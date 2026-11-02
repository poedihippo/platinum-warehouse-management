<?php

namespace App\Interfaces\Services\Loyalty\PointsTransaction;

use App\Interfaces\Services\BaseServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PointsServiceInterface extends BaseServiceInterface
{
    public function balance(string $userId): array;

    public function transactions(string $userId): LengthAwarePaginator;

    public function balanceFor(string $userId): int;
}
