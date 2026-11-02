<?php

namespace App\Interfaces\Repositories\Loyalty\PrizeCategory;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface PrizeCategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function activeList(): Collection;

    public function listWithPrizeCount(): Collection;
}
