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

    /**
     * Document number template of this company, rendered by NumberingService.
     *
     * Only the segment order differs per company: PAS puts the company code first,
     * PA puts the document type first.
     */
    public function invoiceNumberFormat(): string
    {
        return match ($this->value) {
            self::PAS => '{company}/{type}/{month}/{year}/{sequence}',
            self::PA => '{type}/{company}/{month}/{year}/{sequence}',
        };
    }
}
