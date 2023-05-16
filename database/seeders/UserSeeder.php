<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\PersonalAccessToken;
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
        Role::create([
            'id' => 1,
            'name' => 'admin',
            // 'guard_name' => 'web',
        ]);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin'),
            'type' => UserType::Admin,
            'phone' => '0987654321',
        ]);

        PersonalAccessToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'default',
            'token' => '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918',
            'plain_text_token' => 'admin',
            'abilities' => ["*"],
        ]);

        // $superAdmin->assignRole($superAdminRole);
    }
}
