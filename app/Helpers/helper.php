<?php

namespace App\Helpers;

class Helper
{
    public static function rupiah(int|string $number = null, $formatCurrency = false): string
    {
        if (is_null($number)) {
            return number_format(0, 0, ',', '.');
        }

        if ($formatCurrency) {
            return "Rp " . number_format((float) $number, 0, ',', '.');
        }

        return number_format((float) $number, 0, ',', '.');
    }
}
