<?php

namespace App\Enums;

enum DiscountType: string
{
    use BaseEnum;

    case NOMINAL = 'nominal';

    case PERCENTAGE = 'percentage';
}
