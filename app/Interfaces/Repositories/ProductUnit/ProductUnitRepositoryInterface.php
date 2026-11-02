<?php

namespace App\Interfaces\Repositories\ProductUnit;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductUnitRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function findForShow(int|string $id): ProductUnit;

    public function countGenerateQr(array $ids): int;

    public function findGroupingOrFail(int|string $id, int|string $referId): ProductUnit;

    public function paginateUserPrices(ProductUnit $productUnit, int $userId, int $perPage): LengthAwarePaginator;

    public function findForStockProductUnit(int|string|null $stockProductUnitId): ?ProductUnit;

    public function paginateLoyaltyPoints(?string $search, int $perPage): LengthAwarePaginator;

    public function searchForClaims(?string $term): Collection;
}
