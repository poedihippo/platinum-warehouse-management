<?php

namespace App\Http\Repositories\VoucherGenerateBatch;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\VoucherGenerateBatch\VoucherGenerateBatchRepositoryInterface;
use App\Models\VoucherGenerateBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VoucherGenerateBatchRepository extends BaseRepository implements VoucherGenerateBatchRepositoryInterface
{
    public function __construct(VoucherGenerateBatch $model)
    {
        parent::__construct($model);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(VoucherGenerateBatch::class)
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
            ])
            ->allowedIncludes('user')
            ->allowedSorts(['id', 'user_id', 'created_at'])
            ->paginate($perPage);
    }
}
