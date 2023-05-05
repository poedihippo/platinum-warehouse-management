<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Get Token.
     *
     * Get an user token for authentication.
     */
    public function token(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $checkToken = PersonalAccessToken::where('plain_text_token', request()->bearerToken())->first();

        $validatePassword = true;
        $user = User::where('email', $request->email)->first();

        if ($checkToken) {
            $validatePassword = true;
        } else {
            $validatePassword = Hash::check($request->password, $user?->password);
        }

        if (!$user || !$validatePassword) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // return $user->createToken('default')->plainTextToken;
        $token = $user->tokens()->latest()->first()->plain_text_token ?? $user->createToken('default')->plainTextToken;
        return response()->json(['data' => ['token' => $token]]);
    }

    /**
     * Register user.
     *
     * Manual user register
     */
    public function register(Request $request){
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'phone' => 'required',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'phone' => $request->phone,
        ]);

        return $user;
    }
}
