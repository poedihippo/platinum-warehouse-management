<?php

namespace App\Interfaces\Services\Loyalty\Redemption;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\LoyaltyUser;
use App\Models\Loyalty\Redemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RedemptionServiceInterface extends BaseServiceInterface
{
    public function redeem(LoyaltyUser $user, array $input, bool $saveAddress): Redemption;

    public function paginateForUser(string $userId, ?string $status, int $perPage): LengthAwarePaginator;

    public function findOwned(string $userId, string $id, bool $withPrize = true): Redemption;

    public function confirmDelivered(string $userId, string $id): Redemption;
}
