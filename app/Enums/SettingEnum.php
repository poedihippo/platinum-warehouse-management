<?php

namespace App\Enums;

enum SettingEnum: string
{
    use BaseEnum;

    case SO_NUMBER = 'so_number';

    case DO_NUMBER = 'do_number';

    case TAX_VALUE = 'tax_value';

    case BANK_NAME = 'bank_name';

    case BANK_HOLDER = 'bank_holder';

    case BANK_ACCOUNT = 'bank_account';

    public static function getValueType(string $key, string|int $value)
    {
        return match ($key) {
            self::SO_NUMBER->value,
            self::DO_NUMBER->value => (string) $value,
            self::TAX_VALUE->value => (int) $value,
            default => $value
        };
    }
}
