<?php

namespace App\Interfaces\Services\Loyalty\Redemption;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\Redemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RedemptionServiceInterface extends BaseServiceInterface
{
    public function redeem(LoyaltyUser $user, mixed $input, mixed $saveAddress): Redemption;

    public function paginateForUser(mixed $userId, mixed $status, int $perPage): LengthAwarePaginator;

    public function findOwned(mixed $userId, mixed $id, mixed $withPrize = true): Redemption;

    public function confirmDelivered(mixed $userId, mixed $id): Redemption;
}
