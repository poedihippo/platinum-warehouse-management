<?php

namespace Database\Seeders;

use App\Enums\UserLevelEnum;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $superAdminRole = Role::create([
            'id' => 1,
            'name' => 'admin',
            'guard_name' => 'sanctum',
            // 'company_id' => $albaCompany->id
        ]);

        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@gmail.com',
            'password' => bcrypt('superadmin'),
            'level' => UserLevelEnum::SUPER_ADMIN,
        ]);

        $superAdmin->assignRole($superAdminRole);
    }
}
