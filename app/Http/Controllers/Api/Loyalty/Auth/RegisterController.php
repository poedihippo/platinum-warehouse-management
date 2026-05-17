<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Events\Loyalty\LoyaltyUserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\LoyaltyRegisterRequest;
use App\Http\Resources\Loyalty\LoyaltyUserResource;
use App\Models\Loyalty\LoyaltyUser;

class RegisterController extends Controller
{
    public function __invoke(LoyaltyRegisterRequest $request)
    {
        // Password is hashed by the model's password mutator.
        $user = LoyaltyUser::create($request->only('name', 'email', 'password'));

        // Listener sends the 24h signed verification email.
        event(new LoyaltyUserRegistered($user));

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan cek email Anda untuk verifikasi.',
            'data' => new LoyaltyUserResource($user),
        ], 201);
    }
}
