<?php

namespace App\Interfaces\Repositories\Loyalty\Prize;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Loyalty\Prize;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PrizeRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateCatalog(mixed $search, mixed $categoryId, mixed $sort, mixed $maxPointsCost, int $perPage): LengthAwarePaginator;

    public function findActive(mixed $id): ?Prize;

    public function lockForUpdate(mixed $id): ?Prize;

    public function paginateForAdmin(mixed $isActive, mixed $search, mixed $sort, int $perPage): LengthAwarePaginator;
}
