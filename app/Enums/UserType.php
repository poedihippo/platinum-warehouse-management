<?php

namespace App\Enums;

enum UserType: string
{
    use BaseEnum;

    case Admin = 'admin';

    case Reseller = 'reseller';

    case Customer = 'customer';

    case Dealer = 'dealer';

    case CustomerEvent = 'customer_event';

    case Spg = 'spg';
}
