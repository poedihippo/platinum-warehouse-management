<?php

namespace App\Http\Repositories\Loyalty\PrizeCategory;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Loyalty\PrizeCategory\PrizeCategoryRepositoryInterface;
use App\Models\Loyalty\PrizeCategory;
use Illuminate\Support\Collection;

class PrizeCategoryRepository extends BaseRepository implements PrizeCategoryRepositoryInterface
{
    public function __construct(PrizeCategory $model)
    {
        parent::__construct($model);
    }

    public function activeList(): Collection
    {
        return PrizeCategory::active()->orderBy('name')->get(['id', 'name']);
    }

    public function listWithPrizeCount(): Collection
    {
        return PrizeCategory::withCount('prizes')->orderBy('name')->get();
    }
}
