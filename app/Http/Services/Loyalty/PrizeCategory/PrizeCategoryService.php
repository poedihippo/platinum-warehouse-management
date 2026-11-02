<?php

namespace App\Http\Services\Loyalty\PrizeCategory;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Loyalty\PrizeCategory\PrizeCategoryRepositoryInterface;
use App\Interfaces\Services\Loyalty\PrizeCategory\PrizeCategoryServiceInterface;
use Illuminate\Support\Collection;

class PrizeCategoryService extends BaseService implements PrizeCategoryServiceInterface
{
    public function __construct(protected PrizeCategoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function activeList(): Collection
    {
        return $this->repository->activeList();
    }

    public function listWithPrizeCount(): Collection
    {
        return $this->repository->listWithPrizeCount();
    }
}
