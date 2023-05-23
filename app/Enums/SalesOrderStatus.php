<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Pending()
 * @method static static Processing()
 * @method static static Processed()
 * @method static static Done()
 * @method static static Cancelled()
 */
final class SalesOrderStatus extends Enum
{
    const PENDING = 'pending';
    const PROCESSING = 'processing';
    const PROCESSED = 'processed';
    const DONE = 'done';
    const CANCELLED = 'cancelled';
}
