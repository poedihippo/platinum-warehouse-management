<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

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
    }
}
