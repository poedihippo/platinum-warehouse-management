<?php

namespace App\Interfaces\Repositories\ProductUnit;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductUnitRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function findForShow(mixed $id): ProductUnit;

    public function countGenerateQr(mixed $ids): int;

    public function findGroupingOrFail(mixed $id, mixed $referId): ProductUnit;

    public function paginateUserPrices(ProductUnit $productUnit, mixed $userId, int $perPage): LengthAwarePaginator;

    public function findForStockProductUnit(mixed $stockProductUnitId): ?ProductUnit;

    public function paginateLoyaltyPoints(mixed $search, int $perPage): LengthAwarePaginator;

    public function searchForClaims(mixed $term): Collection;
}
