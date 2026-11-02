<?php

namespace App\Http\Controllers\Api;

use App\Interfaces\Services\Auth\AuthServiceInterface;
use App\Models\ProductUnit;
use App\Models\StockProductUnit;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(private AuthServiceInterface $service)
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

        $token = $this->service->issueToken($request->email, $request->password, $request->input('device_name'));

        return response()->json(['data' => ['token' => $token]]);
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
        $this->service->logout($request->user());

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
            if (! $stockProductUnit) {
                $productUnit->update([
                    'deleted_at' => now(),
                ]);

                StockProductUnit::where('product_unit_id', $productUnit->id)->update([
                    'deleted_at' => now(),
                ]);
            }
        }
        exit('mantullll');
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
