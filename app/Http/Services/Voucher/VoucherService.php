<?php

namespace App\Http\Services\Voucher;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Imports\VoucherImport;
use App\Interfaces\Repositories\Voucher\VoucherRepositoryInterface;
use App\Interfaces\Services\Voucher\VoucherServiceInterface;
use App\Models\Voucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class VoucherService extends BaseService implements VoucherServiceInterface
{
    public function __construct(protected VoucherRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateIndex($perPage);
    }

    public function createVoucher(array $data): Voucher
    {
        if (empty($data['code'])) {
            $data['code'] = $this->generateUniqueCode();
        }

        return $this->repository->create($data);
    }

    /**
     * Sales orders store the applied codes in raw_source, so a code change is
     * written back to every sales order using the voucher.
     */
    public function updateVoucher(Voucher $voucher, array $data): Voucher
    {
        $oldCode = $voucher->code;
        $voucher->update($data);

        if ($oldCode !== $voucher->code) {
            $voucher->salesOrders()->each(function ($so) {
                $rawSource = $so->raw_source ?? [];
                $rawSource['voucher_codes'] = $so->vouchers()->pluck('code')->values()->all();
                $so->update(['raw_source' => $rawSource]);
            });
        }

        return $voucher;
    }

    public function deleteUnlessUsed(Voucher $voucher): void
    {
        $this->ensureNotUsed($voucher);

        $voucher->delete();
    }

    public function forceDeleteUnlessUsed(int|string $id): void
    {
        $voucher = $this->repository->findWithTrashedOrFail($id);
        $this->ensureNotUsed($voucher);

        $voucher->forceDelete();
    }

    public function restoreById(int|string $id): Voucher
    {
        $voucher = $this->repository->findWithTrashedOrFail($id);
        $voucher->restore();

        return $voucher;
    }

    public function import(mixed $voucherCategoryId, mixed $description, mixed $file): int
    {
        $import = new VoucherImport($voucherCategoryId, $description);
        $import->import($file);

        return $import->getTotalInserted();
    }

    public function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while ($this->repository->codeExists($code));

        return $code;
    }

    private function ensureNotUsed(Voucher $voucher): void
    {
        if ($voucher->is_used) {
            throw new ApiResponseException('Cannot delete used voucher. Voucher already used!', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
