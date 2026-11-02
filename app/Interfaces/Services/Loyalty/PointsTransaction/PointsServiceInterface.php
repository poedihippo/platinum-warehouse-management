<?php

namespace App\Interfaces\Services\Loyalty\PointsTransaction;

use App\Interfaces\Services\BaseServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PointsServiceInterface extends BaseServiceInterface
{
    public function balance(mixed $userId): array;

    public function transactions(mixed $userId): LengthAwarePaginator;

    public function balanceFor(mixed $userId): int;
}
