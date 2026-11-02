<?php

namespace App\Http\Services\TemporaryStock;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\TemporaryStock\TemporaryStockRepositoryInterface;
use App\Interfaces\Services\TemporaryStock\TemporaryStockServiceInterface;

class TemporaryStockService extends BaseService implements TemporaryStockServiceInterface
{
    public function __construct(protected TemporaryStockRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function scan(mixed $stockId): void
    {
        $this->repository->create(['id' => $stockId, 'created_by_id' => auth()->id()]);
    }
}
