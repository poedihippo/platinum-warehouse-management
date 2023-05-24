<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static PENDING()
 * @method static static PROCESSING()
 * @method static static PROCESSED()
 * @method static static DONE()
 * @method static static CANCELLED()
 */
final class SalesOrderStatus extends Enum
{
    const PENDING = 'pending';
    const PROCESSING = 'processing';
    const PROCESSED = 'processed';
    const DONE = 'done';
    const CANCELLED = 'cancelled';
}
