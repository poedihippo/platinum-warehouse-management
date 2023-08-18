<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Admin()
 * @method static static Reseller()
 * @method static static Customer()
 */
final class UserType extends Enum
{
    const Admin = 1;
    const Reseller = 2;
    const Customer = 3;
    const Dealer = 3;
}
