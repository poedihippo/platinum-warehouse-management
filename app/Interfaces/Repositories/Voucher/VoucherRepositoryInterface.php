<?php

namespace App\Interfaces\Repositories\Voucher;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\Voucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VoucherRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function codeExists(mixed $code): bool;

    public function findWithTrashedOrFail(mixed $id): Voucher;
}
