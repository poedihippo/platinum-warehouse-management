<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class LoyaltyPermissionSeeder extends Seeder
{
    /**
     * Registers the loyalty admin permissions. Idempotent — safe to run on
     * an already-seeded database. Does not assign the permission to any
     * role; that is done manually after deploy.
     */
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'manage loyalty points',
            'guard_name' => 'web',
        ]);

        // Phase 4 — prize catalog CRUD and redemption queue access.
        // Not assigned to any role here; granted manually after deploy.
        Permission::firstOrCreate([
            'name' => 'manage prizes',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'review redemptions',
            'guard_name' => 'web',
        ]);

        // Gates the claims queue (ClaimReviewController + the
        // product-unit search it uses for line-item entry). No existing
        // UserSeeder role is loyalty-aware, so this is assigned only to
        // role 'admin' here — everyone else needs a manual grant.
        $reviewClaims = Permission::firstOrCreate([
            'name' => 'review claims',
            'guard_name' => 'web',
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && !$adminRole->hasPermissionTo($reviewClaims)) {
            $adminRole->givePermissionTo($reviewClaims);
        }
    }
}
