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

    public function findForProductLookup(mixed $id): ?Stock;

    public function deleteAvailable(mixed $stockProductUnitId, mixed $limit): mixed;

    public function paginateDetails(mixed $filter, int $perPage): LengthAwarePaginator;

    public function findForShow(mixed $id, mixed $include): Stock;

    public function updateWithChildren(Stock $stock, mixed $data): int;

    public function deleteWithChildren(mixed $ids): mixed;

    public function updateWithChildrenIn(mixed $ids, mixed $data): int;

    public function updateIn(mixed $ids, mixed $data): int;

    public function lastGroupSequence(StockProductUnit $stockProductUnit): int;

    public function printAll(mixed $filter, mixed $printWithParent): Collection;

    public function findForPublicVerification(mixed $ulid): ?Stock;

    public function findPrintedForPreview(mixed $ids): Collection;
}
