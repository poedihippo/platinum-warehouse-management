<?php

namespace App\Http\Services\VoucherGenerateBatch;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\VoucherGenerateBatch\VoucherGenerateBatchRepositoryInterface;
use App\Interfaces\Services\VoucherGenerateBatch\VoucherGenerateBatchServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VoucherGenerateBatchService extends BaseService implements VoucherGenerateBatchServiceInterface
{
    public function __construct(protected VoucherGenerateBatchRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateIndex($perPage);
    }

    /**
     * Creates the batch and `value` vouchers coded "{batch id}-{index}".
     */
    public function generate(mixed $data, mixed $value, mixed $voucherCategoryId, mixed $startDate, mixed $endDate): void
    {
        DB::beginTransaction();
        try {
            $voucherGenerateBatch = $this->repository->create($data);
            for ($i = 0; $i < $value; $i++) {
                $voucherGenerateBatch->vouchers()->create([
                    'voucher_category_id' => $voucherCategoryId,
                    'code' => $voucherGenerateBatch->id.'-'.$i,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            throw new ApiResponseException($e->getMessage(), 500);
        }
    }
}
