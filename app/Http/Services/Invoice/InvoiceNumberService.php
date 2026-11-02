<?php

namespace App\Http\Services\Invoice;

use App\Interfaces\Repositories\SalesOrder\SalesOrderRepositoryInterface;
use App\Interfaces\Services\Invoice\InvoiceNumberServiceInterface;
use App\Models\Warehouse;

/**
 * IIPE invoice numbers: daily per warehouse, starting at START_INVOICE_NO.
 * Known issue kept as is: no lock is taken while reading the last number.
 */
class InvoiceNumberService implements InvoiceNumberServiceInterface
{
    public function __construct(protected SalesOrderRepositoryInterface $salesOrderRepository)
    {
    }

    public function getDefaultInvoiceNo(string $warehouseCode): string
    {
        return sprintf(config('app.format_invoice_no'), date('Y'), date('m'), date('d'), sprintf('%04s', config('app.start_invoice_no', 90)), $warehouseCode);
    }

    public function getSoNumber(Warehouse $warehouse): string
    {
        $lastInoviceNo = $this->salesOrderRepository->query()->where('is_invoice', true)
            ->whereDate('created_at', date('Y-m-d'))
            ->where('warehouse_id', $warehouse->id)
            ->where('invoice_no', 'like', '%'.config('app.format_invoice_prefix').'%')
            ->orderByDesc('invoice_no')
            ->first(['invoice_no']);

        if ($lastInoviceNo) {
            try {
                $lastInoviceNo = explode('/', $lastInoviceNo->invoice_no)[3];
                $lastInoviceNo = sprintf(config('app.format_invoice_no'), date('Y'), date('m'), date('d'), sprintf('%04s', (int) $lastInoviceNo + 1), $warehouse->code);
            } catch (\Exception $e) {
                $lastInoviceNo = $this->getDefaultInvoiceNo($warehouse->code);
            }
        } else {
            $lastInoviceNo = $this->getDefaultInvoiceNo($warehouse->code);
        }

        return $lastInoviceNo;
    }
}
