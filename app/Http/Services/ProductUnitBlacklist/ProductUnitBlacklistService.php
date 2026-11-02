<?php

namespace App\Http\Services\ProductUnitBlacklist;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\ProductUnitBlacklist\ProductUnitBlacklistRepositoryInterface;
use App\Interfaces\Services\ProductUnitBlacklist\ProductUnitBlacklistServiceInterface;

class ProductUnitBlacklistService extends BaseService implements ProductUnitBlacklistServiceInterface
{
    public function __construct(protected ProductUnitBlacklistRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function blacklist(mixed $productUnitIds): int
    {
        $dataInserted = 0;
        foreach ($productUnitIds as $id) {
            if (! $this->repository->existsForProductUnit($id)) {
                $this->repository->create(['product_unit_id' => $id]);
                $dataInserted++;
            }
        }

        return $dataInserted;
    }

    public function deleteByProductUnitId(mixed $productUnitId): void
    {
        $this->repository->deleteByProductUnitId($productUnitId);
    }
}
