<?php declare(strict_types=1);

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
}
