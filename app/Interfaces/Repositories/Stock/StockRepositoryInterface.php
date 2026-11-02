<?php

namespace App\Interfaces\Repositories\Stock;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Stock;
use App\Models\StockProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StockRepositoryInterface extends BaseRepositoryInterface
{
    public const FORMAT_GROUPING = '%s GR-%s%s';

    public function findForProductLookup(string $id): ?Stock;

    public function deleteAvailable(int|string $stockProductUnitId, int $limit): mixed;

    public function paginateDetails(mixed $filter, int $perPage): LengthAwarePaginator;

    public function findForShow(string $id, string $include): Stock;

    public function updateWithChildren(Stock $stock, array $data): int;

    public function deleteWithChildren(mixed $ids): mixed;

    public function updateWithChildrenIn(array $ids, array $data): int;

    public function updateIn(array $ids, array $data): int;

    public function lastGroupSequence(StockProductUnit $stockProductUnit): int;

    public function printAll(mixed $filter, mixed $printWithParent): Collection;

    public function findForPublicVerification(string $ulid): ?Stock;

    public function findPrintedForPreview(array $ids): Collection;
}
