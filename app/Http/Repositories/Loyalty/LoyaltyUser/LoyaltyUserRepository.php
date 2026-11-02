<?php

namespace App\Http\Repositories\Loyalty\LoyaltyUser;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Loyalty\LoyaltyUser\LoyaltyUserRepositoryInterface;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\PointsTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoyaltyUserRepository extends BaseRepository implements LoyaltyUserRepositoryInterface
{
    public function __construct(LoyaltyUser $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(mixed $email): ?LoyaltyUser
    {
        return LoyaltyUser::where('email', $email)->first();
    }

    public function paginateForAdmin(?string $search, ?bool $isActive, ?string $sort, int $perPage): LengthAwarePaginator
    {
        $query = LoyaltyUser::query()->addSelect([
            'points_balance' => PointsTransaction::selectRaw(
                "COALESCE(SUM(CASE WHEN direction = 'earn' THEN amount ELSE -amount END), 0)"
            )->whereColumn('loyalty_user_id', 'loyalty_users.id'),
        ]);

        if ($search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        match ($sort) {
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'points_asc' => $query->orderBy('points_balance'),
            'points_desc' => $query->orderByDesc('points_balance'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate($perPage);
    }

    public function findWithCounts(string $id): ?LoyaltyUser
    {
        return LoyaltyUser::withCount(['claims', 'redemptions'])->find($id);
    }
}
