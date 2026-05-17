<?php

namespace App\Http\Middleware;

use App\Support\Loyalty\LoyaltySignedUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drop-in replacement for the built-in 'signed' middleware on the
 * loyalty verify-email route.
 *
 * The verification link in the email points at the FRONTEND
 * (verify.platinumadisentosa.com) and is signed against that host by
 * {@see LoyaltySignedUrl}. The frontend then calls back into this API
 * route. Laravel's ValidateSignature recomputes the HMAC against
 * request()->url() — i.e. the API host — so it would always reject a
 * frontend-signed link. This middleware re-derives the signature against
 * the frontend canonical string instead, then applies the same expiry
 * check Laravel does.
 *
 * Throws InvalidSignatureException (HTTP 403) on any mismatch, matching
 * the behaviour the previous 'signed' middleware exposed to clients.
 */
class ValidateLoyaltySignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) $request->route('id');
        $hash = (string) $request->route('hash');
        $expires = $request->query('expires');
        $signature = (string) $request->query('signature', '');

        if ($signature === '' || $expires === null) {
            throw new InvalidSignatureException;
        }

        $expected = LoyaltySignedUrl::expectedVerifyEmailSignature($id, $hash, $expires);

        if (! hash_equals($expected, $signature)) {
            throw new InvalidSignatureException;
        }

        // Same rule as Illuminate\Routing\UrlGenerator::signatureHasNotExpired.
        if (now()->getTimestamp() > (int) $expires) {
            throw new InvalidSignatureException;
        }

        return $next($request);
    }
}
