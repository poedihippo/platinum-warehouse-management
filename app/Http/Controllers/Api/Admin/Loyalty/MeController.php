<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * The loyalty permissions this endpoint reports on. Built via
     * $user->can() rather than $user->getAllPermissions() so the
     * Gate::before role-'admin' bypass (AuthServiceProvider) is reflected
     * even when the role holds no explicit permission rows.
     */
    private const KNOWN_PERMISSIONS = [
        'manage prizes',
        'review redemptions',
        'manage loyalty points',
        'review claims',
    ];

    /**
     * GET /api/admin/loyalty/me
     *
     * The current admin's real loyalty permissions, as a flat array —
     * unlike /api/users/me, whose 'permissions' object is a fixed
     * boolean taxonomy that excludes the loyalty permission set entirely.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $permissions = collect(self::KNOWN_PERMISSIONS)
            ->filter(fn (string $permission) => $user->can($permission))
            ->values();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $permissions,
            ],
        ]);
    }
}
