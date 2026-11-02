<?php

namespace App\Http\Repositories\Loyalty\Redemption;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Loyalty\Redemption\RedemptionRepositoryInterface;
use App\Models\Loyalty\Redemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RedemptionRepository extends BaseRepository implements RedemptionRepositoryInterface
{
    public function __construct(Redemption $model)
    {
        parent::__construct($model);
    }

    public function hasPending(string $userId, mixed $prizeId): bool
    {
        return Redemption::where('loyalty_user_id', $userId)
            ->where('prize_id', $prizeId)
            ->where('status', Redemption::STATUS_PENDING)
            ->exists();
    }

    public function paginateForUser(string $userId, ?string $status, int $perPage): LengthAwarePaginator
    {
        $query = Redemption::with('prize')
            ->where('loyalty_user_id', $userId)
            ->orderByDesc('submitted_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function findWithPrize(string $id): ?Redemption
    {
        return Redemption::with('prize')->find($id);
    }

    public function paginateForReview(?string $status, ?string $search, ?string $sort, int $perPage): LengthAwarePaginator
    {
        $query = Redemption::with(self::REVIEW_EAGER_LOAD);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($search !== null) {
            $query->where(function ($sub) use ($search) {
                $sub->whereHas('loyaltyUser', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%");
                })->orWhereHas('prize', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            });
        }

        match ($sort) {
            'submitted_at_asc' => $query->orderBy('submitted_at', 'asc'),
            'submitted_at_desc' => $query->orderBy('submitted_at', 'desc'),
            default => $query->orderBy('submitted_at', 'desc'),
        };

        return $query->paginate($perPage);
    }

    public function findForReview(string $id): ?Redemption
    {
        return Redemption::with(self::REVIEW_EAGER_LOAD)->find($id);
    }
}
