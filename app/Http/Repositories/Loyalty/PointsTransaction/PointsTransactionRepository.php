<?php

namespace App\Http\Repositories\Loyalty\PointsTransaction;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Loyalty\PointsTransaction\PointsTransactionRepositoryInterface;
use App\Models\Loyalty\PointsTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PointsTransactionRepository extends BaseRepository implements PointsTransactionRepositoryInterface
{
    public function __construct(PointsTransaction $model)
    {
        parent::__construct($model);
    }

    public function balanceFor(mixed $userId): int
    {
        $earned = (int) PointsTransaction::where('loyalty_user_id', $userId)
            ->where('direction', PointsTransaction::DIRECTION_EARN)
            ->sum('amount');

        $spent = (int) PointsTransaction::where('loyalty_user_id', $userId)
            ->where('direction', PointsTransaction::DIRECTION_SPEND)
            ->sum('amount');

        return $earned - $spent;
    }

    public function paginateForUser(mixed $userId, int $perPage): LengthAwarePaginator
    {
        return PointsTransaction::where('loyalty_user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
