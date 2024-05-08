<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Models\Voucher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\VoucherCategory;

class VoucherCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $voucherCategory = VoucherCategory::create([
            'name' => 'Voucher Category Nominal',
            'discount_type' => DiscountType::NOMINAL,
            'discount_amount' => 100000,
            'description' => 'Voucher Category Nominal',
        ]);

        for ($i = 0; $i <= 3; $i++) {
            Voucher::create([
                'voucher_category_id' => $voucherCategory->id,
                'code' => 'NOM-00' . $i,
                'description' => 'NOM-00' . $i,
            ]);
        }

        $voucherCategory = VoucherCategory::create([
            'name' => 'Voucher Category Percentage',
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_amount' => 10,
            'description' => 'Voucher Category Percentage',
        ]);

        for ($i = 0; $i <= 3; $i++) {
            Voucher::create([
                'voucher_category_id' => $voucherCategory->id,
                'code' => 'PERC-00' . $i,
                'description' => 'PERC-00' . $i,
            ]);
        }
    }
}
