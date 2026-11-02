<?php

namespace App\Interfaces\Services\Loyalty\LoyaltyUser;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerManagementServiceInterface extends BaseServiceInterface
{
    public function paginate(mixed $search, mixed $isActive, mixed $sort, int $perPage): LengthAwarePaginator;

    public function findWithBalance(mixed $id): ?LoyaltyUser;

    public function adjustPoints(LoyaltyUser $customer, mixed $points, mixed $reason, mixed $adminId): LoyaltyUser;

    public function toggleActive(LoyaltyUser $customer): LoyaltyUser;
}
