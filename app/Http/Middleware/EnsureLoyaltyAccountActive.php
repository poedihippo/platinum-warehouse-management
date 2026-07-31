<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks every auth:loyalty route (redemptions, claims, profile, ...) for
 * a deactivated account, including requests using a token that was
 * already issued before deactivation. Applied alongside auth:loyalty
 * rather than per-endpoint, so redeem/claim are covered without
 * duplicating the check in each controller.
 */
class EnsureLoyaltyAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            return response()->json(['message' => 'Akun dinonaktifkan.'], 403);
        }

        return $next($request);
    }
}
