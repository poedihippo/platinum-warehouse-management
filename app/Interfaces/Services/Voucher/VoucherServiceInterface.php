<?php

namespace App\Interfaces\Services\Voucher;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Voucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VoucherServiceInterface extends BaseServiceInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function createVoucher(mixed $data): Voucher;

    public function updateVoucher(Voucher $voucher, mixed $data): Voucher;

    public function deleteUnlessUsed(Voucher $voucher): void;

    public function forceDeleteUnlessUsed(mixed $id): void;

    public function restoreById(mixed $id): Voucher;

    public function import(mixed $voucherCategoryId, mixed $description, mixed $file): int;

    public function generateUniqueCode(): string;
}
