<?php

namespace App\Interfaces\Services\Voucher;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Voucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VoucherServiceInterface extends BaseServiceInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function createVoucher(array $data): Voucher;

    public function updateVoucher(Voucher $voucher, array $data): Voucher;

    public function deleteUnlessUsed(Voucher $voucher): void;

    public function forceDeleteUnlessUsed(int|string $id): void;

    public function restoreById(int|string $id): Voucher;

    public function import(mixed $voucherCategoryId, mixed $description, mixed $file): int;

    public function generateUniqueCode(): string;
}
