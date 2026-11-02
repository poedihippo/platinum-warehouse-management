<?php

namespace App\Interfaces\Services\StockTransfer;

use App\Interfaces\Services\BaseServiceInterface;

interface StockTransferServiceInterface extends BaseServiceInterface
{
    public function transfer(mixed $input, mixed $isQr): void;
}
