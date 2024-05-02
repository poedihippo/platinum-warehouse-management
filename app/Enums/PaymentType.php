<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static CASH()
 * @method static static TRANSFER()
 * @method static static CREDIT_CARD()
 */
final class PaymentType extends Enum
{
    const CASH = 'cash';
    const TRANSFER = 'transfer';
    const CREDIT_CARD = 'credit_card';
}
