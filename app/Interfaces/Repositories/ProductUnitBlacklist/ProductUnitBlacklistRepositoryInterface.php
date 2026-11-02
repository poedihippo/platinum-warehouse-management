<?php

namespace App\Interfaces\Repositories\ProductUnitBlacklist;

use App\Interfaces\Repositories\BaseRepositoryInterface;

interface ProductUnitBlacklistRepositoryInterface extends BaseRepositoryInterface
{
    public function existsForProductUnit(mixed $productUnitId): bool;

    public function deleteByProductUnitId(mixed $productUnitId): mixed;
}
