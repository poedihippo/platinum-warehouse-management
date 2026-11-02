<?php

namespace App\Http\Services\VoucherCategory;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\VoucherCategory\VoucherCategoryRepositoryInterface;
use App\Interfaces\Services\VoucherCategory\VoucherCategoryServiceInterface;
use App\Models\VoucherCategory;

class VoucherCategoryService extends BaseService implements VoucherCategoryServiceInterface
{
    public function __construct(protected VoucherCategoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function findWithTrashedOrFail(int|string $id): VoucherCategory
    {
        return $this->repository->findWithTrashedOrFail($id);
    }
}
