<?php

namespace App\Interfaces\Repositories\Loyalty\Prize;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Loyalty\Prize;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PrizeRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateCatalog(?string $search, ?string $categoryId, ?string $sort, ?int $maxPointsCost, int $perPage): LengthAwarePaginator;

    public function findActive(string $id): ?Prize;

    public function lockForUpdate(mixed $id): ?Prize;

    public function paginateForAdmin(?bool $isActive, ?string $search, ?string $sort, int $perPage): LengthAwarePaginator;
}
