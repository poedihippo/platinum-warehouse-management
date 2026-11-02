<?php

namespace App\Interfaces\Services\ProductUnitBlacklist;

use App\Interfaces\Services\BaseServiceInterface;

interface ProductUnitBlacklistServiceInterface extends BaseServiceInterface
{
    public function blacklist(array $productUnitIds): int;

    public function deleteByProductUnitId(int|string $productUnitId): void;
}
