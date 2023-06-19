<?php

namespace Database\Seeders;

use App\Enums\SettingEnum;
use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Setting::create([
            'key' => SettingEnum::SO_NUMBER,
            'value' => sprintf('PAS/SO/%s/%s/01', date('m'), date('y'))
        ]);

        Setting::create([
            'key' => SettingEnum::DO_NUMBER,
            'value' => sprintf('PAS/DO/%s/%s/01', date('m'), date('y'))
        ]);
    }
}
