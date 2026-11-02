<?php

namespace App\Interfaces\Services\Loyalty\LoyaltyUser;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerManagementServiceInterface extends BaseServiceInterface
{
    public function paginate(?string $search, ?bool $isActive, ?string $sort, int $perPage): LengthAwarePaginator;

    public function findWithBalance(string $id): ?LoyaltyUser;

    public function adjustPoints(LoyaltyUser $customer, int $points, string $reason, mixed $adminId): LoyaltyUser;

    public function toggleActive(LoyaltyUser $customer): LoyaltyUser;
}
