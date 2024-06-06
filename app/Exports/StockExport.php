<?php

namespace App\Exports;

use App\Models\ProductUnit;
use App\Models\Warehouse;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class StockExport implements FromView
{
    public function view(): View
    {
        $productUnits = ProductUnit::get(['id', 'code', 'name', 'price']);
        return view('exports.stock', [
            'productUnits' => $productUnits,
            'warehouses' => Warehouse::get(['id', 'name']),
        ]);
    }
}
