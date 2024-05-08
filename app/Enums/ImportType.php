<?php

namespace App\Enums;
use BenSampo\Enum\Enum;

final class ImportType extends Enum
{
    const VOUCHER = 'voucher';

    public function getImporter(): string
    {
        return match ($this->value) {
            self::VOUCHER => \App\Imports\VoucherImport::class,
            default => null
        };
    }

    public function getExporter(): string
    {
        return match ($this->value) {
            self::VOUCHER => \App\Exports\VoucherExport::class,
            default => null
        };
    }

    public function getModel(): string
    {
        return match ($this->value) {
            self::VOUCHER => \App\Models\Voucher::class,
            default => null
        };
    }
}
