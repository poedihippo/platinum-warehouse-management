<?php

namespace Database\Seeders;

use App\Enums\UserType;
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
            // 'guard_name' => 'sanctum',
        ]);

        $superAdmin = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin'),
            'type' => UserType::Admin,
            'phone' => '0987654321',
            'provider_id' => '8484840303',
            'provider_name' => 'Google'
        ]);

        // $superAdmin->assignRole($superAdminRole);
    }
}
