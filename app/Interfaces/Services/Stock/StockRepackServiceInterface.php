<?php

namespace App\Interfaces\Services\Stock;

interface StockRepackServiceInterface
{
    public function repack(mixed $id, mixed $qty, mixed $targetStockProductUnitId, mixed $createdAt): string;
}
