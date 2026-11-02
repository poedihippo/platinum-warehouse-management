<?php

namespace App\Interfaces\Repositories\ProductUnitBlacklist;

use App\Interfaces\Repositories\BaseRepositoryInterface;

interface ProductUnitBlacklistRepositoryInterface extends BaseRepositoryInterface
{
    public function existsForProductUnit(int|string $productUnitId): bool;

    public function deleteByProductUnitId(int|string $productUnitId): mixed;
}
