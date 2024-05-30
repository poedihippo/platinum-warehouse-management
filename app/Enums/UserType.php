<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Admin()
 * @method static static Reseller()
 * @method static static Customer()
 * @method static static Dealer()
 * @method static static CustomerEvent()
 */
final class UserType extends Enum
{
    const Admin = 'admin';
    const Reseller = 'reseller';
    const Customer = 'customer';
    const Dealer = 'dealer';
    const CustomerEvent = 'customer_event';
    const Spg = 'spg';
}
