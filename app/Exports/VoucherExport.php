<?php

namespace App\Exports;

use App\Models\Voucher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VoucherExport implements FromCollection, WithHeadings
{
    const HEADINGS = [
        'code',
        'description (optional)',
    ];

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Voucher::all();
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    public static function getSample(): Collection
    {
        return collect([
            self::HEADINGS,
            [
                "CODE1",
                "Description for voucher (optional)"
            ],
            [
                "CODE2",
            ],
            [
                "CODE3",
            ],
            [
                "CODE4",
            ],
        ]);
    }
}
