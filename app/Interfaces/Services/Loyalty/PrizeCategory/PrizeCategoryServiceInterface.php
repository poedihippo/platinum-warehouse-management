<?php

namespace App\Interfaces\Services\Loyalty\PrizeCategory;

use App\Interfaces\Services\BaseServiceInterface;
use Illuminate\Support\Collection;

interface PrizeCategoryServiceInterface extends BaseServiceInterface
{
    public function activeList(): Collection;

    public function listWithPrizeCount(): Collection;
}
