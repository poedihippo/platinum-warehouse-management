<?php

namespace App\Exports;

use App\Models\ProductUnit;
use App\Models\Warehouse;
use DateInterval;
use DatePeriod;
use DateTime;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class StockHistoryExport implements FromView
{
    public function __construct(private string $startDate, private string $endDate)
    {
    }

    public function view(): View
    {
        $dates = [];
        $timePeriod = new DatePeriod(
            new DateTime($this->startDate),
            new DateInterval('P1D'),
            new DateTime(date('Y-m-d', strtotime($this->endDate . ' +1 day'))),
        );

        foreach ($timePeriod as $key => $value) {
            $date = $value->format('Y-m-d');
            $dates[] = $date;
        }

        $productUnits = ProductUnit::get(['id', 'code', 'name', 'price']);
        return view('exports.stockHistory', [
            'productUnits' => $productUnits,
            'dates' => $dates,
            'warehouses' => Warehouse::get(['id', 'name']),
        ]);
    }
}
