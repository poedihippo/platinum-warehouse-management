<?php

namespace App\Services;

use App\Enums\SettingEnum;
use Illuminate\Support\Facades\DB;

class SettingService
{
    /**
     * validation total price between BE calculation with FE calculation
     *
     * @param int|float $totalPrice total price from FE calculation
     * @param array $items SO items data
     *
     * @return bool
     */
    public static function bankTransferInfo(): string
    {
        $text = '';
        $bankName = DB::table('settings')->where('key', SettingEnum::BANK_NAME)->first(['value'])->value ?? 'BCA';
        $bankHolder = DB::table('settings')->where('key', SettingEnum::BANK_HOLDER)->first(['value'])->value ?? 'PT. Platinum Adi Sentosa';
        $bankAccount = DB::table('settings')->where('key', SettingEnum::BANK_ACCOUNT)->first(['value'])->value ?? '2883123808';

        $text = $bankName . ' An. ' . $bankHolder . '<br>' . $bankAccount;
        return $text;
    }
}
