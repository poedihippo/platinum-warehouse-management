<?php

namespace App\Support\Loyalty;

use DateTimeInterface;

/**
 * Builds (and re-derives the signature for) loyalty links that point at
 * the customer FRONTEND rather than the API.
 *
 * Laravel's URL::temporarySignedRoute / ValidateSignature compute the
 * HMAC against the route's API URL (driven by APP_URL). Our emails must
 * link to verify.platinumadisentosa.com instead, and the frontend then
 * calls BACK into the API to confirm. If we signed against the API URL
 * the host wouldn't match on the way back; if we just rewrote the host
 * after signing, the signature would no longer validate.
 *
 * So we mint the signature against the FRONTEND canonical string here,
 * and {@see \App\Http\Middleware\ValidateLoyaltySignature} re-derives it
 * the same way. The crypto is identical to Laravel's (sha256 HMAC over
 * "<url>?expires=<ts>", keyed by config('app.key')); only the host/path
 * are pinned to the frontend. Generation and validation MUST stay in
 * sync — keep both paths going through this class.
 */
class LoyaltySignedUrl
{
    /**
     * Signed email-verification link e-mailed to the customer:
     *
     *   {frontend}/verify-email/{id}/{hash}?expires=<ts>&signature=<hmac>
     *
     * The frontend passes id/hash/expires/signature back to
     * GET /api/loyalty/auth/verify-email/{id}/{hash}.
     */
    public static function verifyEmail(string $id, string $hash, DateTimeInterface $expiresAt): string
    {
        $expires = $expiresAt->getTimestamp();
        $url = static::frontendBase() . "/verify-email/{$id}/{$hash}";

        return $url . '?expires=' . $expires . '&signature=' . static::sign($url, $expires);
    }

    /**
     * Expected signature for an inbound API request whose link was minted
     * by {@see verifyEmail()}. Pulls id/hash from the route and expires
     * from the query, and re-derives the HMAC against the same frontend
     * canonical string used at generation time.
     */
    public static function expectedVerifyEmailSignature(string $id, string $hash, int|string $expires): string
    {
        $url = static::frontendBase() . "/verify-email/{$id}/{$hash}";

        return static::sign($url, (int) $expires);
    }

    /**
     * Password-reset link e-mailed to the customer:
     *
     *   {frontend}/reset-password/{token}?email=<email>
     *
     * No HMAC here — Laravel's password broker already keys the token by
     * email, so the broker's own reset() call is the verification step.
     * The frontend passes token + email to
     * POST /api/loyalty/auth/password-reset/confirm.
     */
    public static function passwordReset(string $token, string $email): string
    {
        return static::frontendBase()
            . '/reset-password/' . rawurlencode($token)
            . '?email=' . urlencode($email);
    }

    /**
     * Mirrors Illuminate\Routing\UrlGenerator's signing scheme: sha256
     * HMAC over the canonical "<url>?expires=<ts>" string, keyed by the
     * same value Laravel's signer uses (raw config('app.key')).
     */
    protected static function sign(string $urlWithoutQuery, int $expires): string
    {
        return hash_hmac('sha256', $urlWithoutQuery . '?expires=' . $expires, (string) config('app.key'));
    }

    protected static function frontendBase(): string
    {
        return rtrim((string) config('loyalty.frontend_url'), '/');
    }
}
