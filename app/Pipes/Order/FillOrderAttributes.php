<?php

namespace App\Pipes\Order;

use App\Models\SalesOrder;

class FillOrderAttributes
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $rawSoruce = $salesOrder->raw_source;

        $salesOrder->expected_price = empty($rawSoruce['expected_price']) ? null : $rawSoruce['expected_price'];
        $salesOrder->reseller_id = $rawSoruce['reseller_id'] ?? $salesOrder->reseller_id;
        $salesOrder->spg_id = isset($rawSoruce['spg_id']) || isset($salesOrder->spg_id) ? ($salesOrder->spg_id ? $salesOrder->spg_id : $rawSoruce['spg_id']) : null;
        $salesOrder->warehouse_id = $rawSoruce['warehouse_id'] ?? $salesOrder->warehouse_id;

        if (request()->segment(2) == 'invoices' && request()->method() === 'PUT') {
            $salesOrder->invoice_no = isset($rawSoruce['invoice_no']) ? $rawSoruce['invoice_no'] : $salesOrder->invoice_no;
        } else {
            $salesOrder->invoice_no = isset($rawSoruce['invoice_no']) ? ($salesOrder->invoice_no ? $salesOrder->invoice_no : $rawSoruce['invoice_no']) : null;
        }
        $salesOrder->transaction_date = $rawSoruce['transaction_date'] ?? $salesOrder->transaction_date ?? now();
        $salesOrder->shipment_estimation_datetime = isset($rawSoruce['shipment_estimation_datetime']) ? $rawSoruce['shipment_estimation_datetime'] : now();
        $salesOrder->shipment_fee = $rawSoruce['shipment_fee'];
        $salesOrder->additional_discount = $rawSoruce['additional_discount'] ?? 0;
        $salesOrder->description = $rawSoruce['description'] ?? "#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih";
        $salesOrder->type = $rawSoruce['type'] ?? null;

        return $next($salesOrder);
    }
}
