<?php

namespace App\Interfaces\Services\StockOpname;

use App\Models\StockOpnameDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StockOpnameDetailServiceInterface
{
    public function paginate(int $stockOpnameId, int $perPage): LengthAwarePaginator;

    public function find(int $stockOpnameId, mixed $stockOpnameDetailId): StockOpnameDetail;

    public function create(int $stockOpnameId, mixed $productUnitId): StockOpnameDetail;

    public function updateDetail(int $stockOpnameId, mixed $id, array $data): mixed;

    public function scan(int $stockOpnameId, mixed $stockOpnameDetailId, mixed $stockId, mixed $isScanned): string;

    public function setDone(int $stockOpnameId, mixed $id, mixed $isDone): StockOpnameDetail;
}
