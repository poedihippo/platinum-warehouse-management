<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductUnit;
use App\Models\StockProductUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Spatie role names that are loyalty-only. A user whose roles are a
     * non-empty subset of this list — and nothing else — gets a
     * loyalty-scoped token instead of a full-access one.
     */
    private const LOYALTY_ONLY_ROLES = [
        'loyalty manager',
        'loyalty reviewer',
        'loyalty prize manager',
        'loyalty fulfillment',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get Token.
     *
     * Get a user token for authentication. Always mints a fresh token —
     * never reuses or reads back an existing one.
     */
    public function token(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $deviceName = $request->input('device_name') ?: 'default';
        $token = $user->createToken($deviceName, $this->tokenAbilitiesFor($user))->plainTextToken;

        return response()->json(['data' => ['token' => $token]]);
    }

    /**
     * ['loyalty'] if every role the user holds is in LOYALTY_ONLY_ROLES
     * (and they hold at least one); ['*'] for everyone else — any
     * warehouse role, role 'admin', or no role at all.
     */
    private function tokenAbilitiesFor(User $user): array
    {
        $roleNames = $user->roles->pluck('name');

        $isLoyaltyOnly = $roleNames->isNotEmpty()
            && $roleNames->diff(self::LOYALTY_ONLY_ROLES)->isEmpty();

        return $isLoyaltyOnly ? ['loyalty'] : ['*'];
    }

    /**
     * Logout.
     *
     * Revoke the token used for this request. Mirrors
     * Api\Loyalty\Auth\LogoutController's shape (top-level 'message', no
     * 'data' wrapper).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
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
