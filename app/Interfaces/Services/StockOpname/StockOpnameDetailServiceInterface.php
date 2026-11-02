<?php

namespace App\Interfaces\Services\StockOpname;

use App\Models\StockOpnameDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StockOpnameDetailServiceInterface
{
    public function paginate(mixed $stockOpnameId, int $perPage): LengthAwarePaginator;

    public function find(mixed $stockOpnameId, mixed $stockOpnameDetailId): StockOpnameDetail;

    public function create(mixed $stockOpnameId, mixed $productUnitId): StockOpnameDetail;

    public function updateDetail(mixed $stockOpnameId, mixed $id, mixed $data): mixed;

    public function scan(mixed $stockOpnameId, mixed $stockOpnameDetailId, mixed $stockId, mixed $isScanned): string;

    public function setDone(mixed $stockOpnameId, mixed $id, mixed $isDone): StockOpnameDetail;
}
