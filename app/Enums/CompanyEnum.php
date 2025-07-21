<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static PAS()
 * @method static static PA()
 */
final class CompanyEnum extends Enum
{
    const PAS = 'pas';
    const PA = 'pa';
}
