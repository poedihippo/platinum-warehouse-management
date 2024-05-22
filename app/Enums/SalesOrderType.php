<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static DEFAULT()
 * @method static static DELIVERY()
 * @method static static PICKUP()
 * @method static static FREE()
 */
final class SalesOrderType extends Enum
{
    const DEFAULT = 'default';
    const DELIVERY = 'delivery';
    const PICKUP = 'pickup';
    const FREE = 'free';
}
