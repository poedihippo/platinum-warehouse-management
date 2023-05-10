<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirectToProvider($provider)
    {
        // dd(Socialite::driver($provider));
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function handleProvideCallback($provider)
    {
        try {

            $user = Socialite::driver($provider)->stateless()->user();
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed'
            ], Response::HTTP_UNAUTHORIZED);
        }
        // find or create user and send params user get from socialite and provider
        $authUser = $this->findOrCreateUser($user, $provider);

        // login user
        Auth()->login($authUser, true);

        // setelah login redirect ke dashboard
        return response()->json([
            'message' => 'SUCCESS',
            'user' => $authUser,

        ], Response::HTTP_ACCEPTED);
    }

    public function findOrCreateUser($socialUser, $provider)
    {
        // Get Social Account
        $user = User::where('provider_id', $socialUser->getId())
            ->where('provider_name', $provider)
            ->first();

        // Jika sudah ada
        if (!$user) {
            // User berdasarkan email 
            $user = User::where('email', $socialUser->getEmail())->first();

            // Jika Tidak ada user
            if (!$user) {
                // Create user baru
                $user = User::create([
                    'name'  => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'provider_id'   => $socialUser->getId(),
                    'provider_name' => $provider
                ]);
            } else {
                // Buat Social Account baru
                $user->update([
                    'provider_id'   => $socialUser->getId(),
                    'provider_name' => $provider
                ]);
            }
        }
        return $user;
    }
}
