<?php

namespace App\Interfaces\Repositories\Loyalty\Redemption;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Loyalty\Redemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RedemptionRepositoryInterface extends BaseRepositoryInterface
{
    public const REVIEW_EAGER_LOAD = ['loyaltyUser:id,name,email,phone', 'prize:id,name,photo_path'];

    public function hasPending(string $userId, mixed $prizeId): bool;

    public function paginateForUser(string $userId, ?string $status, int $perPage): LengthAwarePaginator;

    public function findWithPrize(string $id): ?Redemption;

    public function paginateForReview(?string $status, ?string $search, ?string $sort, int $perPage): LengthAwarePaginator;

    public function findForReview(string $id): ?Redemption;
}
