<?php

namespace App\Http\Repositories\VoucherCategory;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\VoucherCategory\VoucherCategoryRepositoryInterface;
use App\Models\VoucherCategory;

class VoucherCategoryRepository extends BaseRepository implements VoucherCategoryRepositoryInterface
{
    public function __construct(VoucherCategory $model)
    {
        parent::__construct($model);
    }

    public function findWithTrashedOrFail(int|string $id): VoucherCategory
    {
        return VoucherCategory::withTrashed()->findOrFail($id);
    }
}
