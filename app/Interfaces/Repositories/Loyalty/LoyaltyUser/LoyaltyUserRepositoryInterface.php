<?php

namespace App\Interfaces\Repositories\Loyalty\LoyaltyUser;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LoyaltyUserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(mixed $email): ?LoyaltyUser;

    public function paginateForAdmin(mixed $search, mixed $isActive, mixed $sort, int $perPage): LengthAwarePaginator;

    public function findWithCounts(mixed $id): ?LoyaltyUser;
}
