<?php

namespace App\Listeners\Loyalty;

use App\Events\Loyalty\LoyaltyUserRegistered;
use App\Mail\Loyalty\VerifyEmailMail;
use App\Support\Loyalty\LoyaltySignedUrl;
use Illuminate\Support\Facades\Mail;

class SendLoyaltyVerificationEmail
{
    public function handle(LoyaltyUserRegistered $event): void
    {
        $user = $event->loyaltyUser;

        // 24h signed URL pointing at the loyalty FRONTEND verify page.
        // The frontend passes id/hash/expires/signature back to the API
        // verify-email endpoint. sha1(email) ties the link to the current
        // email address: changing the email invalidates an outstanding
        // link. See LoyaltySignedUrl / ValidateLoyaltySignature.
        $verificationUrl = LoyaltySignedUrl::verifyEmail(
            (string) $user->getKey(),
            sha1($user->email),
            now()->addHours(24),
        );

        Mail::to($user->email)->send(new VerifyEmailMail($user->name, $verificationUrl));
    }
}
