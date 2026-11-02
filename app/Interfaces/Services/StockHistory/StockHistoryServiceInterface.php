<?php

namespace App\Interfaces\Services\StockHistory;

use App\Interfaces\Services\BaseServiceInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface StockHistoryServiceInterface extends BaseServiceInterface
{
    public function export(mixed $startDate, mixed $endDate): BinaryFileResponse;
}
