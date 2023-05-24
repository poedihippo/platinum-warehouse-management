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
        $adminRole = Role::create([
            'id' => 1,
            'name' => 'admin',
            // 'guard_name' => 'web',
        ]);

        $user = User::create([
            'name' => 'Admin',
            'code' => '1234567890',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin'),
            'tax_address' => 'jalan kenangan',
            'provider_id' => '1',
            'provider_name' => 'Iconic',
            'city' => 'Tangerang',
            'province' => 'Tangerang',
            'zip_code' => '56311',
            'country' => 'Indonesia',
            'phone' => '08577700702',
            'contact_person' => 'Ryan Dmasiv',
            'web_page' => 'jalan kenangan',
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

        $user->assignRole($adminRole);

        User::create([
            'name' => 'Emporium Fish',
            'code' => 'CE007',
            'email' => 'emporium.fish@gmail.com',
            'password' => bcrypt('12345678'),
            'type' => UserType::Reseller,
            'phone' => '098709870987',
        ]);

        User::create([
            'name' => 'Customer Pameran',
            'code' => 'CC001',
            'email' => 'customer.pameran@gmail.com',
            'password' => bcrypt('12345678'),
            'type' => UserType::Reseller,
            'phone' => '098765098765',
        ]);
    }
}
