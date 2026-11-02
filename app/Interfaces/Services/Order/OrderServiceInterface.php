<?php

namespace App\Interfaces\Services\Order;

use App\Models\SalesOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderServiceInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function store(array $rawSource, mixed $isPreview): SalesOrder;

    public function convert(SalesOrder $order, array $rawSource, mixed $isPreview): SalesOrder;

    public function destroyQuotation(SalesOrder $order): void;
}
