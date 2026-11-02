<?php

namespace App\Interfaces\Services\Stock;

interface StockGroupingServiceInterface
{
    public function groupByScan(array $input): void;

    public function group(array $input): void;

    public function ungroup(string $id): void;
}
