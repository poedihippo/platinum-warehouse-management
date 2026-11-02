<?php

namespace App\Interfaces\Services\StockTransfer;

use App\Interfaces\Services\BaseServiceInterface;

interface StockTransferServiceInterface extends BaseServiceInterface
{
    public function transfer(array $input, bool $isQr): void;
}
