<?php

namespace App\Http\Repositories\StockProductUnit;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\StockProductUnit\StockProductUnitRepositoryInterface;
use App\Models\StockProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockProductUnitRepository extends BaseRepository implements StockProductUnitRepositoryInterface
{
    public function __construct(StockProductUnit $model)
    {
        parent::__construct($model);
    }

    public function paginateStockIndex(int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(StockProductUnit::select('stock_product_units.id', 'stock_product_units.qty', 'stock_product_units.product_unit_id', 'stock_product_units.warehouse_id')
            ->tenanted()
            ->has('productUnit')
            ->has('warehouse')
            ->with([
                'warehouse' => fn ($q) => $q->select('warehouses.id', 'warehouses.name'),
                'productUnit' => fn ($q) => $q->with([
                    'uom' => fn ($q) => $q->select('id', 'name'),
                    'product' => fn ($q) => $q->select('id', 'product_category_id', 'product_brand_id', 'name')->with([
                        'productCategory' => fn ($q) => $q->select('id', 'name'),
                        'productBrand' => fn ($q) => $q->select('id', 'name'),
                    ]),
                ]),
            ])
            ->withCount(['stocks' => fn ($q) => $q->whereCountableStock()]))
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('product_unit_id'),
                AllowedFilter::scope('product_unit'),
                AllowedFilter::scope('company', 'whereCompany'),
                AllowedFilter::callback('is_product_unit_qr', function ($q, $value) {
                    filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $q->whereProductUnitQr(1)
                        : $q->whereProductUnitQr(0);
                }),
            )
            ->allowedSorts(
                'id',
                'created_at',
            )
            ->paginate($perPage);
    }
}
