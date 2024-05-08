<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static UPLOAD()
 * @method static static IMPORT()
 */
final class BatchSource extends Enum
{
    const UPLOAD = 'upload';
    const IMPORT = 'import';
}
