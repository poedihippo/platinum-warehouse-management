<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static NOMINAL()
 * @method static static PERCENTAGE()
 */
final class DiscountType extends Enum
{
    const NOMINAL = 'nominal';
    const PERCENTAGE = 'percentage';
}
