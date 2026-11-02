<?php

namespace App\Http\Repositories\Stock;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Stock\StockRepositoryInterface;
use App\Models\Stock;
use App\Models\StockProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockRepository extends BaseRepository implements StockRepositoryInterface
{
    public function __construct(Stock $model)
    {
        parent::__construct($model);
    }

    public function findForProductLookup(string $id): ?Stock
    {
        return $this->query()->select('id', 'stock_product_unit_id', 'expired_date')->find($id);
    }

    public function deleteAvailable(int|string $stockProductUnitId, int $limit): mixed
    {
        return Stock::whereAvailableStock()->where('stock_product_unit_id', $stockProductUnitId)->limit($limit)->delete();
    }

    public function paginateDetails(mixed $filter, int $perPage): LengthAwarePaginator
    {
        if (isset($filter) && ! empty($filter['parent_id'])) {
            $query = Stock::tenanted()->whereNotNull('parent_id');
        } else {
            $query = Stock::tenanted();
        }

        if (isset($filter) && ! empty($filter['is_show_group']) && $filter['is_show_group'] == 1) {
            $query->withCount('childs');
        } else {
            $query->isShowGroup(0);
        }

        return QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('id'),
                AllowedFilter::exact('printer_id'),
                AllowedFilter::exact('parent_id'),
                AllowedFilter::exact('stock_product_unit_id'),
                AllowedFilter::exact('receive_order_id'),
                AllowedFilter::exact('receive_order_detail_id'),
                AllowedFilter::scope('is_show_group'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
                AllowedFilter::callback('show_all', function (\Illuminate\Database\Eloquent\Builder $query, $value) {
                    if (! $value == 0) {
                        $query->whereAvailableStock();
                    }
                }),
                'in_printing_queue',
            ])
            ->allowedSorts([
                'id',
                'in_printing_queue',
                'printed_at',
                'scanned_count',
                'scanned_datetime',
                'created_at',
            ])
            ->paginate($perPage);
    }

    public function findForShow(string $id, string $include): Stock
    {
        $includeChilds = str_contains($include, 'childs');
        $includeReceiveOrder = str_contains($include, 'receiveOrder');
        $includeSalesOrder = str_contains($include, 'salesOrder');
        $includeSDeliveryOrder = str_contains($include, 'deliveryOrder');

        $stock = Stock::query()
            ->when($includeChilds, fn ($q) => $q->with('childs', fn ($q) => $q->select('id', 'parent_id')))
            ->when($includeReceiveOrder, fn ($q) => $q->with('receiveOrderDetail'))
            ->when(
                $includeSalesOrder,
                fn ($q) => $q->with(
                    'salesOrderItem',
                    fn ($q) => $q
                        ->with(
                            'salesOrderDetail',
                            fn ($q) => $q
                                ->select('id', 'sales_order_id')
                                ->with('salesOrder', fn ($q) => $q->select('id', 'invoice_no'))
                        )
                        ->when(
                            $includeSDeliveryOrder,
                            fn ($q) => $q->with(
                                'deliveryOrderDetail',
                                fn ($q) => $q->select('id', 'delivery_order_id', 'sales_order_detail_id')
                                    ->with('deliveryOrder', fn ($q) => $q->select('id', 'invoice_no'))
                            )
                        )
                )
            )
            ->findTenanted($id);

        return $stock->load([
            'stockProductUnit' => fn ($q) => $q->tenanted()->withCount(['stocks' => fn ($q) => $q->whereAvailableStock()->whereNull('description')]),
        ]);
    }

    public function updateWithChildren(Stock $stock, array $data): int
    {
        return Stock::where('parent_id', $stock->id)->orWhere('id', $stock->id)->update($data);
    }

    public function deleteWithChildren(mixed $ids): mixed
    {
        return Stock::whereIn('id', $ids)->orWhereIn('parent_id', $ids)->delete();
    }

    public function updateWithChildrenIn(array $ids, array $data): int
    {
        return Stock::whereIn('id', $ids)->orWhereIn('parent_id', $ids)->update($data);
    }

    public function updateIn(array $ids, array $data): int
    {
        return Stock::whereIn('id', $ids)->update($data);
    }

    /**
     * Sequence of the last group created this month for the stock product unit,
     * read from the "{code} GR-{mmyy}{NNN}" group description.
     */
    public function lastGroupSequence(StockProductUnit $stockProductUnit): int
    {
        $lastGroupName = $stockProductUnit->stocks()->whereNull('parent_id')->has('childs')->whereMonth('stocks.created_at', date('m'))->whereYear('stocks.created_at', date('Y'))->orderByDesc('description')->first(['description'])?->description ?? '';
        $lastOrderNumberGroup = explode(' ', $lastGroupName);
        $lastOrderNumberGroup = $lastOrderNumberGroup[1] ?? '';
        if ($lastOrderNumberGroup) {
            $lastOrderNumberGroup = (int) substr($lastOrderNumberGroup, -3);
        } else {
            $lastOrderNumberGroup = 000;
        }

        return $lastOrderNumberGroup;
    }

    public function printAll(mixed $filter, mixed $printWithParent): Collection
    {
        $query = Stock::tenanted()->select('id', 'parent_id', 'qr_code', 'description');

        if (isset($filter) && isset($filter['stock_product_unit_id']) && $filter['stock_product_unit_id'] != '') {
            $query->where('stock_product_unit_id', $filter['stock_product_unit_id']);
        }

        if (isset($filter) && isset($filter['receive_order_detail_id']) && $filter['receive_order_detail_id'] != '') {
            $query->where('receive_order_detail_id', $filter['receive_order_detail_id']);
        }

        if (isset($filter) && isset($filter['parent_id']) && $filter['parent_id'] != '') {
            if ($printWithParent == 1) {
                $query->where('id', $filter['parent_id'])->orWhere('parent_id', $filter['parent_id'])->orderBy('parent_id');
            } else {
                $query->where('parent_id', $filter['parent_id']);
            }
        } else {
            $query->whereNull('parent_id');
        }

        return QueryBuilder::for($query)
            ->allowedFilters(['id', 'receive_order_detail_id', 'stock_product_unit_id', 'parent_id'])
            ->allowedSorts(['created_at'])
            ->get();
    }

    public function findForPublicVerification(string $ulid): ?Stock
    {
        return Stock::with([
            'stockProductUnit:id,product_unit_id',
            'stockProductUnit.productUnit:id,product_id,name',
            'stockProductUnit.productUnit.product:id,product_brand_id',
            'stockProductUnit.productUnit.product.productBrand' => function ($query) {
                $query->select('id', 'name', 'logo_path')->whereNull('deleted_at');
            },
        ])
            ->select(['id', 'stock_product_unit_id', 'expired_date'])
            ->find($ulid);
    }

    public function findPrintedForPreview(array $ids): Collection
    {
        return Stock::with(['stockProductUnit' => fn ($q) => $q->select('id', 'product_unit_id')->with('productUnit', fn ($q) => $q->select('id', 'name'))])
            ->whereIn('id', $ids)->whereNotNull('printed_at')->get(['id', 'printed_at', 'stock_product_unit_id']);
    }
}
