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
    const Pending = 'pending';
    const Processing = 'processing';
    const Processed = 'processed';
    const Done = 'done';
    const Cancelled = 'cancelled';
}
