<?php

namespace App\Exports;

use App\Models\SalesOrder;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

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
