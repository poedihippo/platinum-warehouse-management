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
     *
     * Hand-maintained — there's no group/tag column on the Permission
     * model to derive "loyalty permissions" from at runtime, and the
     * canonical list in LoyaltyPermissionSeeder is a seeder, not a
     * queryable source. Every new loyalty permission must be added here
     * too, or /me silently omits it and the verify frontend has nothing
     * to gate on (this has happened twice now — see 'manage brands').
     */
    private const KNOWN_PERMISSIONS = [
        'manage prizes',
        'review redemptions',
        'manage loyalty points',
        'review claims',
        'manage brands',
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
