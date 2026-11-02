<?php

namespace App\Enums;

use App\Exports\VoucherExport;
use App\Imports\VoucherImport;
use App\Models\Voucher;

enum ImportType: string
{
    use BaseEnum;

    case VOUCHER = 'voucher';

    public function getImporter(): string
    {
        return match ($this) {
            self::VOUCHER => VoucherImport::class,
            default => null
        };
    }

    public function getExporter(): string
    {
        return match ($this) {
            self::VOUCHER => VoucherExport::class,
            default => null
        };
    }

    public function getModel(): string
    {
        return match ($this) {
            self::VOUCHER => Voucher::class,
            default => null
        };
    }
}
