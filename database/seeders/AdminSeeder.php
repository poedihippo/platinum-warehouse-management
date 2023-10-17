<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $adminRole = Role::create([
            'id' => 1,
            'name' => 'admin',
            // 'guard_name' => 'web',
        ]);

        $user = User::create([
            'name' => 'Admin',
            'code' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => 'admin',
            'contact_person' => 'admin',
            'type' => UserType::Admin,
        ]);

        PersonalAccessToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'default',
            'token' => '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918',
            'plain_text_token' => 'admin',
            'abilities' => ["*"],
        ]);

        $user->assignRole($adminRole);

        // User::create([
        //     'name' => 'Emporium Fish',
        //     'code' => 'CE007',
        //     'email' => 'emporium.fish@gmail.com',
        //     'password' => '12345678',
        //     'type' => UserType::Reseller,
        //     'phone' => '098709870987',
        // ]);

        // User::create([
        //     'name' => 'Customer Pameran',
        //     'code' => 'CC001',
        //     'email' => 'customer.pameran@gmail.com',
        //     'password' => '12345678',
        //     'type' => UserType::Reseller,
        //     'phone' => '098765098765',
        // ]);
    }
}
