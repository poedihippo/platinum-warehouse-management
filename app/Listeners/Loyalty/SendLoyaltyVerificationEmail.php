<?php

namespace App\Listeners\Loyalty;

use App\Events\Loyalty\LoyaltyUserRegistered;
use App\Mail\Loyalty\VerifyEmailMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendLoyaltyVerificationEmail
{
    public function handle(LoyaltyUserRegistered $event): void
    {
        $user = $event->loyaltyUser;

        // 24h signed URL pointing at the backend verify endpoint.
        // hash() ties the link to the current email address: changing
        // the email invalidates an outstanding link.
        $verificationUrl = URL::temporarySignedRoute(
            'loyalty.verification.verify',
            now()->addHours(24),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->email),
            ]
        );

        Mail::to($user->email)->send(new VerifyEmailMail($user->name, $verificationUrl));
    }
}
