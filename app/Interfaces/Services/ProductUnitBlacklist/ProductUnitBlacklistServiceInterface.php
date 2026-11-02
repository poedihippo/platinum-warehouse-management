<?php

namespace App\Interfaces\Services\ProductUnitBlacklist;

use App\Interfaces\Services\BaseServiceInterface;

interface ProductUnitBlacklistServiceInterface extends BaseServiceInterface
{
    public function blacklist(mixed $productUnitIds): int;

    public function deleteByProductUnitId(mixed $productUnitId): void;
}
