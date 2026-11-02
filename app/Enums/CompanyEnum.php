<?php

namespace App\Enums;

enum CompanyEnum: string
{
    use BaseEnum;

    case PAS = 'pas';

    case PA = 'pa';

    /**
     * Document number template of this company, rendered by NumberingService.
     *
     * Only the segment order differs per company: PAS puts the company code first,
     * PA puts the document type first.
     */
    public function invoiceNumberFormat(): string
    {
        return match ($this) {
            self::PAS => '{company}/{type}/{month}/{year}/{sequence}',
            self::PA => '{type}/{company}/{month}/{year}/{sequence}',
        };
    }
}
