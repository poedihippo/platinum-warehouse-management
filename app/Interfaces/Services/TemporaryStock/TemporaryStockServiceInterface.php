<?php

namespace App\Interfaces\Services\TemporaryStock;

use App\Interfaces\Services\BaseServiceInterface;

interface TemporaryStockServiceInterface extends BaseServiceInterface
{
    public function scan(mixed $stockId): void;
}
