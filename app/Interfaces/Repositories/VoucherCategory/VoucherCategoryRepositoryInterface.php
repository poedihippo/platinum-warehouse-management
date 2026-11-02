<?php

namespace App\Interfaces\Repositories\VoucherCategory;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Models\VoucherCategory;

interface VoucherCategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function findWithTrashedOrFail(int|string $id): VoucherCategory;
}
