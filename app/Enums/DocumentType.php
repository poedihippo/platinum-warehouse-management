<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static SO()
 * @method static static DO()
 */
final class DocumentType extends Enum
{
    const SO = 'so';

    const DO = 'do';
}
