<?php

namespace App\Http\Services\Loyalty\LoyaltyUser;

use App\Interfaces\Repositories\Loyalty\LoyaltyUser\LoyaltyUserRepositoryInterface;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyProfileServiceInterface;
use App\Models\Loyalty\LoyaltyUser;

class LoyaltyProfileService implements LoyaltyProfileServiceInterface
{
    public function __construct(protected LoyaltyUserRepositoryInterface $repository)
    {
    }

    public function update(LoyaltyUser $user, mixed $data): LoyaltyUser
    {
        return $this->repository->update($user, $data);
    }
}
