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

    public function findForShow(string $id, string $include): Stock;

    public function updateWithChildren(Stock $stock, array $data): void;

    public function deleteSingle(Stock $stock): void;

    public function bulkDelete(mixed $ids): void;

    public function printAll(mixed $filter, mixed $printWithParent): Collection;

    public function record(mixed $stockIds, mixed $stockProductUnitId, mixed $receiveOrderDetailId, mixed $isPrintAll): string;

    public function import(mixed $file, mixed $warehouseId): void;

    public function setTempel(array $ids, mixed $isTempel): void;

    public function export(): BinaryFileResponse;

    public function setToPrinted(array $ids): void;

    public function setToPrintingQueue(array $ids, mixed $printerId, mixed $expiredDate): void;

    public function findPrintedForPreview(array $ids): Collection;

    public function setIsStock(array $ids, mixed $isAdd): void;

    public function idsAsText(array $ids): string;

    public function findForPublicVerification(string $ulid): ?Stock;
}
