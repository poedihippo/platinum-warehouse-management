<?php

namespace App\Interfaces\Repositories\Loyalty\LoyaltyUser;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LoyaltyUserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(mixed $email): ?LoyaltyUser;

    public function paginateForAdmin(?string $search, ?bool $isActive, ?string $sort, int $perPage): LengthAwarePaginator;

    public function findWithCounts(string $id): ?LoyaltyUser;
}
