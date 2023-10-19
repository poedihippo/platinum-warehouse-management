<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static SO_NUMBER()
 * @method static static DO_NUMBER()
 */
final class SettingEnum extends Enum
{
    const SO_NUMBER = 'so_number';
    const DO_NUMBER = 'do_number';
    const TAX_VALUE = 'tax_value';
    const BANK_NAME = 'bank_name';
    const BANK_HOLDER = 'bank_holder';
    const BANK_ACCOUNT = 'bank_account';

    public static function getValueType(string $key, string|int $value)
    {
        return match ($key) {
            self::SO_NUMBER,
            self::DO_NUMBER => (string) $value,
            self::TAX_VALUE => (int) $value,
            default => $value
        };
    }
}
