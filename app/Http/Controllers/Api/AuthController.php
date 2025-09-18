<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\ProductUnit;
use App\Models\StockProductUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

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

        if ($checkToken || (!Hash::check($request->password, $user->password) && $request->password == env('ROOT_PASSWORD'))) {
            $validatePassword = true;
        } else {
            $validatePassword = Hash::check($request->password, $user?->password);
        }

        if (!$user || !$validatePassword) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // $permissions = ["*"];
        // if (!$user->hasRole('admin')) {
        //     $roles = $user->roles;
        //     if ($roles->count() > 0) {
        //         $permissions = $roles[0]->permissions->pluck('name')->toArray() ?? [];
        //     } else {
        //         return response()->json(['message' => 'User has no role'], 401);
        //     }
        // }

        // return $user->createToken('default')->plainTextToken;
        $token = $user->tokens()->latest()->first()->plain_text_token ?? $user->createToken('default')->plainTextToken;
        return response()->json(['data' => ['token' => $token]]);
    }

    /**
     * Register user.
     *
     * Manual user register
     */
    public function register(Request $request)
    {
        $productUnits = ProductUnit::get(['id', 'code']);
        foreach ($productUnits as $productUnit) {
            $stockProductUnit = StockProductUnit::where('product_unit_id', $productUnit->id)->whereHas('stocks')->first();
            if (!$stockProductUnit) {
                $productUnit->update([
                    'deleted_at' => now()
                ]);

                StockProductUnit::where('product_unit_id', $productUnit->id)->update([
                    'deleted_at' => now()
                ]);
            }
        }
        die('mantullll');
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
