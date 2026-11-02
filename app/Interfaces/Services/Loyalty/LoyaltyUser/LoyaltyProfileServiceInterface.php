<?php

namespace App\Interfaces\Services\Loyalty\LoyaltyUser;

use App\Models\Loyalty\LoyaltyUser;

interface LoyaltyProfileServiceInterface
{
    public function update(LoyaltyUser $user, mixed $data): LoyaltyUser;
}
