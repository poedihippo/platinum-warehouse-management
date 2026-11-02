<?php

namespace App\Http\Repositories\Payment;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Payment\PaymentRepositoryInterface;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    public function __construct(Payment $model)
    {
        parent::__construct($model);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(Payment::tenanted())
            ->allowedFilters(
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('sales_order_id'),
                'type',
            )
            ->allowedIncludes(
                AllowedInclude::callback('user', function ($query) {
                    $query->select('id', 'name');
                }),
                'salesOrder',
            )
            ->allowedSorts('id', 'sales_order_id', 'user_id', 'type', 'created_at')
            ->paginate($perPage);
    }
}
