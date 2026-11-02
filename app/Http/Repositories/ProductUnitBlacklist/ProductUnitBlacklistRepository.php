<?php

namespace App\Http\Repositories\ProductUnitBlacklist;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\ProductUnitBlacklist\ProductUnitBlacklistRepositoryInterface;
use App\Models\ProductUnitBlacklist;

class ProductUnitBlacklistRepository extends BaseRepository implements ProductUnitBlacklistRepositoryInterface
{
    public function __construct(ProductUnitBlacklist $model)
    {
        parent::__construct($model);
    }

    public function existsForProductUnit(int|string $productUnitId): bool
    {
        return $this->query()->where('product_unit_id', $productUnitId)->exists();
    }

    public function deleteByProductUnitId(int|string $productUnitId): mixed
    {
        return $this->query()->where('product_unit_id', $productUnitId)->delete();
    }
}
