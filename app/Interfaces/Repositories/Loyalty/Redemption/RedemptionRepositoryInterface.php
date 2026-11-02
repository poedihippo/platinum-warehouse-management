<?php

namespace App\Interfaces\Repositories\Loyalty\Redemption;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Loyalty\Redemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RedemptionRepositoryInterface extends BaseRepositoryInterface
{
    public const REVIEW_EAGER_LOAD = ['loyaltyUser:id,name,email,phone', 'prize:id,name,photo_path'];

    public function hasPending(mixed $userId, mixed $prizeId): bool;

    public function paginateForUser(mixed $userId, mixed $status, int $perPage): LengthAwarePaginator;

    public function findWithPrize(mixed $id): ?Redemption;

    public function paginateForReview(mixed $status, mixed $search, mixed $sort, int $perPage): LengthAwarePaginator;

    public function findForReview(mixed $id): ?Redemption;
}
