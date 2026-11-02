<?php

namespace App\Interfaces\Services\Stock;

interface StockGroupingServiceInterface
{
    public function groupByScan(mixed $input): void;

    public function group(mixed $input): void;

    public function ungroup(mixed $id): void;
}
