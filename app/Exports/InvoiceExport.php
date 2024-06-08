<?php

namespace App\Exports;

use App\Models\SalesOrder;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class InvoiceExport implements FromView
{
    public function view(): View
    {
        $invoices = SalesOrder::whereInvoice()->get();
        return view('exports.invoice', [
            'invoices' => $invoices,
        ]);
    }
}
