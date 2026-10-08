<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static BLIBLI()
 * @method static static SHOPEE()
 * @method static static TOKOPEDIA()
 */
final class Marketplace extends Enum
{
    const BLIBLI = 'blibli';
    const SHOPEE = 'shopee';
    const TOKOPEDIA = 'tokopedia';
}
