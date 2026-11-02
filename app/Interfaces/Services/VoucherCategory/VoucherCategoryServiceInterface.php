<?php

namespace App\Interfaces\Services\VoucherCategory;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\VoucherCategory;

interface VoucherCategoryServiceInterface extends BaseServiceInterface
{
    public function findWithTrashedOrFail(int|string $id): VoucherCategory;
}
