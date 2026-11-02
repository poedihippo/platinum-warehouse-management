<?php

namespace App\Interfaces\Services\Stock;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface StockServiceInterface extends BaseServiceInterface
{
    public function paginateStockProductUnits(int $perPage): LengthAwarePaginator;

    public function paginateDetails(mixed $filter, int $perPage): LengthAwarePaginator;

    public function findForShow(mixed $id, mixed $include): Stock;

    public function updateWithChildren(Stock $stock, mixed $data): void;

    public function deleteSingle(Stock $stock): void;

    public function bulkDelete(mixed $ids): void;

    public function printAll(mixed $filter, mixed $printWithParent): Collection;

    public function record(mixed $stockIds, mixed $stockProductUnitId, mixed $receiveOrderDetailId, mixed $isPrintAll): string;

    public function import(mixed $file, mixed $warehouseId): void;

    public function setTempel(mixed $ids, mixed $isTempel): void;

    public function export(): BinaryFileResponse;

    public function setToPrinted(mixed $ids): void;

    public function setToPrintingQueue(mixed $ids, mixed $printerId, mixed $expiredDate): void;

    public function findPrintedForPreview(mixed $ids): Collection;

    public function setIsStock(mixed $ids, mixed $isAdd): void;

    public function idsAsText(mixed $ids): string;

    public function findForPublicVerification(mixed $ulid): ?Stock;
}
