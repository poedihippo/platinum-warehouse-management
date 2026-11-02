<?php

namespace App\Interfaces\Services\VoucherGenerateBatch;

use App\Interfaces\Services\BaseServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VoucherGenerateBatchServiceInterface extends BaseServiceInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function generate(array $data, mixed $value, mixed $voucherCategoryId, mixed $startDate, mixed $endDate): void;
}
