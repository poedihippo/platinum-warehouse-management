<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Http\Controllers\Controller;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Signature + expiry are already validated by the 'signed'
     * middleware on the route. We additionally check the email hash so
     * a link is invalidated if the user changes their email.
     */
    public function __invoke(Request $request, string $id, string $hash)
    {
        $user = LoyaltyUser::find($id);

        if (!$user || !hash_equals(sha1($user->email), (string) $hash)) {
            return response()->json(['message' => 'Tautan verifikasi tidak valid.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah diverifikasi sebelumnya.']);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json(['message' => 'Email berhasil diverifikasi.']);
    }
}
