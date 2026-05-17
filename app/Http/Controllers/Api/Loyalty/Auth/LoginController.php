<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\LoyaltyLoginRequest;
use App\Http\Resources\Loyalty\LoyaltyUserResource;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __invoke(LoyaltyLoginRequest $request)
    {
        $user = LoyaltyUser::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau kata sandi salah.'], 422);
        }

        // Token is scoped to the 'loyalty' ability so it can never act
        // on warehouse/admin endpoints even if presented there.
        $token = $user->createToken('loyalty', ['loyalty'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'data' => new LoyaltyUserResource($user),
        ]);
    }
}
