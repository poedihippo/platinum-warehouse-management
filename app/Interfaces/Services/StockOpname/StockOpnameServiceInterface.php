<?php

namespace App\Interfaces\Services\StockOpname;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\StockOpname;
use Illuminate\Support\Collection;

interface StockOpnameServiceInterface extends BaseServiceInterface
{
    public function setDone(StockOpname $stockOpname, mixed $isDone): StockOpname;

    public function setAllDetailsDone(StockOpname $stockOpname, mixed $isDone): void;

    public function items(mixed $stockOpnameDetailId): Collection;

    public function deleteItem(\App\Models\StockOpnameDetail $stockOpnameDetail, mixed $stockId): void;
}
