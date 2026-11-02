<?php

namespace App\Enums;

enum Marketplace: string
{
    use BaseEnum;

    case BLIBLI = 'blibli';

    case SHOPEE = 'shopee';

    case TOKOPEDIA = 'tokopedia';
}
