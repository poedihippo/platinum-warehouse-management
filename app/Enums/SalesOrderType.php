<?php

namespace App\Enums;

enum SalesOrderType: string
{
    use BaseEnum;

    case DEFAULT = 'default';

    case DELIVERY = 'delivery';

    case PICKUP = 'pickup';

    case FREE = 'free';
}
