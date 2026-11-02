<?php

namespace App\Http\Repositories\Voucher;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Voucher\VoucherRepositoryInterface;
use App\Models\Voucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class VoucherRepository extends BaseRepository implements VoucherRepositoryInterface
{
    public function __construct(Voucher $model)
    {
        parent::__construct($model);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(Voucher::class)
            ->allowedFilters([
                AllowedFilter::exact('voucher_generate_batch_id'),
                AllowedFilter::exact('voucher_category_id'),
                'code',
                AllowedFilter::callback('start_date', fn ($q, $v) => $q->where('start_date', '<=', $v)),
                AllowedFilter::callback('end_date', fn ($q, $v) => $q->where('end_date', '>=', $v)),
            ])
            ->allowedIncludes([
                'voucherGenerateBatch',
                AllowedInclude::callback('category', function ($query) {
                    $query->select('id', 'name', 'discount_type', 'discount_amount');
                }),
                AllowedInclude::callback('salesOrders', function ($query) {
                    $query->select('id', 'invoice_no');
                }),
            ])
            ->allowedSorts(['id', 'voucher_category_id', 'name', 'code', 'created_at'])
            ->paginate($perPage);
    }

    public function codeExists(string $code): bool
    {
        return Voucher::withoutGlobalScopes()->where('code', $code)->exists();
    }

    public function findWithTrashedOrFail(int|string $id): Voucher
    {
        return Voucher::withTrashed()->findOrFail($id);
    }
}
