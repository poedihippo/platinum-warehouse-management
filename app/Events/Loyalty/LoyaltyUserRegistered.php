<?php

namespace App\Events\Loyalty;

use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired right after a loyalty customer registers. Listened to by
 * SendLoyaltyVerificationEmail, which dispatches the verification email.
 */
class LoyaltyUserRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public LoyaltyUser $loyaltyUser)
    {
    }
}
