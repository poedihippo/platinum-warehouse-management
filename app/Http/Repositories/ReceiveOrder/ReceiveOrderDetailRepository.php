<?php

namespace App\Http\Repositories\ReceiveOrder;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\ReceiveOrder\ReceiveOrderDetailRepositoryInterface;
use App\Models\ReceiveOrderDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ReceiveOrderDetailRepository extends BaseRepository implements ReceiveOrderDetailRepositoryInterface
{
    public function __construct(ReceiveOrderDetail $model)
    {
        parent::__construct($model);
    }

    public function paginateForReceiveOrder(mixed $receiveOrderId, int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(ReceiveOrderDetail::where('receive_order_id', $receiveOrderId))
            ->allowedFilters(
                AllowedFilter::scope('product_unit'),
                AllowedFilter::callback('product_brand_id', function (Builder $query, $value) {
                    $query->whereHas('productUnit.product', fn ($q) => $q->where('product_brand_id', $value));
                }),
                AllowedFilter::callback('product_category_id', function (Builder $query, $value) {
                    $query->whereHas('productUnit.product', fn ($q) => $q->where('product_category_id', $value));
                }),
            )
            ->paginate($perPage);
    }
}
