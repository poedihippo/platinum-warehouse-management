<?php

namespace App\Enums;

enum PaymentType: string
{
    use BaseEnum;

    case CASH = 'cash';

    case TRANSFER = 'transfer';

    case CREDIT_CARD = 'credit_card';

    case QRIS = 'qris';
}
