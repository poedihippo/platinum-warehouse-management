<?php

namespace App\Interfaces\Services\Invoice;

use App\Models\SalesOrder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface InvoiceServiceInterface
{
    public function ensureStockAvailable(mixed $items): void;

    public function createInvoice(array $data, bool $isPreview): SalesOrder;

    public function findForShow(int|string $id): SalesOrder;

    public function ensureUpdatable(SalesOrder $salesOrder): void;

    public function ensureDeletable(SalesOrder $salesOrder): void;

    public function destroyInvoice(SalesOrder $salesOrder): void;

    public function invoiceNumberFor(mixed $warehouseId): string;

    public function export(): BinaryFileResponse;

    public function cashiers(): Collection;
}
