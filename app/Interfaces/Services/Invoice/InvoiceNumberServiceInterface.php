<?php

namespace App\Interfaces\Services\Invoice;

use App\Models\Warehouse;

interface InvoiceNumberServiceInterface
{
    public function getDefaultInvoiceNo(string $warehouseCode): string;

    public function getSoNumber(Warehouse $warehouse): string;
}
