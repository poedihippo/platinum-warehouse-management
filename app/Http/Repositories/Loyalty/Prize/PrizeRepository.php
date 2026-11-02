<?php

namespace App\Http\Repositories\Loyalty\Prize;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Loyalty\Prize\PrizeRepositoryInterface;
use App\Models\Loyalty\Prize;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PrizeRepository extends BaseRepository implements PrizeRepositoryInterface
{
    public function __construct(Prize $model)
    {
        parent::__construct($model);
    }

    public function paginateCatalog(mixed $search, mixed $categoryId, mixed $sort, mixed $maxPointsCost, int $perPage): LengthAwarePaginator
    {
        $query = Prize::active()->with('category');

        if ($search !== null) {
            $query->where(fn ($q) => $q->where('name', 'like', "%$search%"));
        }

        if ($categoryId !== null) {
            if ($categoryId === 'none') {
                $query->where(function ($q) {
                    $q->whereNull('prize_category_id')
                        ->orWhereDoesntHave('category', fn ($c) => $c->active());
                });
            } else {
                $query->where('prize_category_id', $categoryId)
                    ->whereHas('category', fn ($c) => $c->active());
            }
        }

        match ($sort) {
            'points_desc' => $query->orderByDesc('points_cost'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            default => $query->orderBy('points_cost'),
        };

        if ($maxPointsCost !== null) {
            $query->where('points_cost', '<=', $maxPointsCost);
        }

        return $query->paginate($perPage);
    }

    public function findActive(mixed $id): ?Prize
    {
        return Prize::active()->with('category')->find($id);
    }

    public function lockForUpdate(mixed $id): ?Prize
    {
        return Prize::lockForUpdate()->find($id);
    }

    public function paginateForAdmin(mixed $isActive, mixed $search, mixed $sort, int $perPage): LengthAwarePaginator
    {
        $query = Prize::with('category')->withCount('redemptions');

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($search !== null) {
            $query->where(fn ($q) => $q->where('name', 'like', "%$search%"));
        }

        match ($sort) {
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'points_asc' => $query->orderBy('points_cost'),
            'points_desc' => $query->orderByDesc('points_cost'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate($perPage);
    }
}
