<?php

namespace App\Http\Repositories\ProductUnit;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\ProductUnit\ProductUnitRepositoryInterface;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesOrderDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class ProductUnitRepository extends BaseRepository implements ProductUnitRepositoryInterface
{
    public function __construct(ProductUnit $model)
    {
        parent::__construct($model);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(ProductUnit::with('product')->has('product')->orderBy('id'))
            ->allowedFilters([
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('is_generate_qr'),
                AllowedFilter::exact('is_ppn'),
                AllowedFilter::scope('name', 'search'),
                AllowedFilter::scope('product_brand_id', 'whereProductBrandId'),
                AllowedFilter::scope('product_category_id', 'whereProductCategoryId'),
                AllowedFilter::scope('company', 'whereCompany'),
            ])
            ->allowedIncludes([
                AllowedInclude::callback('relations', fn ($q) => $q->with('relatedProductUnit', fn ($q) => $q->select('id', 'name'))),
                AllowedInclude::callback('refer', fn ($q) => $q->select('id', 'name')),
                AllowedInclude::callback('product', fn ($q) => $q->select('id', 'name', 'product_category_id', 'product_brand_id')->with([
                    'productCategory' => fn ($q) => $q->select('id', 'name'),
                    'productBrand' => fn ($q) => $q->select('id', 'name'),
                ])),
                AllowedInclude::callback('uom', fn ($q) => $q->select('id', 'name')),
            ])
            ->allowedSorts(['id', 'product_id', 'name', 'price', 'created_at'])
            ->paginate($perPage);
    }

    public function findForShow(int|string $id): ProductUnit
    {
        return ProductUnit::with([
            'uom' => fn ($q) => $q->select('id', 'name'),
            'product' => fn ($q) => $q->with([
                'productCategory' => fn ($q) => $q->select('id', 'name'),
                'productBrand' => fn ($q) => $q->select('id', 'name'),
            ]),
            'refer' => fn ($q) => $q->select('id', 'name'),
            'relations' => fn ($q) => $q->with('relatedProductUnit', fn ($q) => $q->select('id', 'name')),
        ])->has('product')->findOrFail($id);
    }

    public function countGenerateQr(array $ids): int
    {
        return $this->query()->whereIn('id', $ids)->where('is_generate_qr', 1)->count();
    }

    public function findGroupingOrFail(int|string $id, int|string $referId): ProductUnit
    {
        return $this->query()->where('id', $id)->where('refer_id', $referId)->firstOrFail();
    }

    public function paginateUserPrices(ProductUnit $productUnit, int $userId, int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(SalesOrderDetail::select('id', 'product_unit_id', 'warehouse_id', 'unit_price', 'created_at'))
            ->with([
                'warehouse' => fn ($q) => $q->select('id', 'name'),
                'productUnit' => fn ($q) => $q->select('id', 'code', 'name'),
            ])
            ->where('product_unit_id', $productUnit->id)
            ->whereHas('salesOrder', fn ($q) => $q->where('reseller_id', $userId))
            ->allowedSorts(['id', 'created_at'])
            ->paginate($perPage);
    }

    public function findForStockProductUnit(int|string|null $stockProductUnitId): ?ProductUnit
    {
        return ProductUnit::select('id', 'product_id', 'uom_id', 'name', 'price', 'code')
            ->with([
                'uom' => fn ($q) => $q->select('id', 'name'),
                'product' => fn ($q) => $q->select('id', 'product_category_id', 'product_brand_id', 'name', 'article_url')->with('productCategory', fn ($q) => $q->select('id', 'name'))->with('productBrand', fn ($q) => $q->select('id', 'name')),
            ])
            ->whereHas('stockProductUnit', fn ($q) => $q->where('id', $stockProductUnitId))
            ->first();
    }

    public function paginateLoyaltyPoints(?string $search, int $perPage): LengthAwarePaginator
    {
        $query = ProductUnit::with(['product:id,name', 'uom:id,name'])
            ->where(function ($q) {
                $q->where('loyalty_eligible', true)
                    ->orWhere('points_per_unit', '>', 0);
            })
            ->orderByRaw('points_per_unit > 0 DESC')
            ->orderBy(Product::select('name')->whereColumn('products.id', 'product_units.product_id'));

        if ($search !== null) {
            $query->search($search);
        }

        return $query->paginate($perPage);
    }

    public function searchForClaims(?string $term): Collection
    {
        $query = ProductUnit::with('product:id,name')
            ->where('points_per_unit', '>', 0);

        if ($term !== null) {
            $this->applyTokenSearch($query, $term);
        }

        return $query->orderBy('name')->limit(20)->get();
    }

    /**
     * Matches the way admins actually read a product: the UI shows
     * "{product.name} - {unit.name}", a string that exists in no single
     * column, so a plain LIKE on the unit alone misses it.
     *
     * Every whitespace-separated token must match somewhere in
     * (product.name OR unit.name OR unit.code) — an AND of ORs. That makes
     * "CZ Aqua - CZ Bacta Extrem", "Bacta Extrem" and "CZM014" all land on
     * the same row without special-casing the joined display string.
     *
     * Not ProductUnit::scopeSearch, which the points screen also uses.
     */
    private function applyTokenSearch(Builder $query, string $term): void
    {
        $tokens = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            // The separator in "CZ Aqua - CZ Bacta Extrem" is its own token
            // and matches nothing, which would AND the whole result to empty.
            if (! preg_match('/[\p{L}\p{N}]/u', $token)) {
                continue;
            }

            $like = '%'.$token.'%';

            $query->where(fn (Builder $q) => $q
                ->where('product_units.name', 'like', $like)
                ->orWhere('product_units.code', 'like', $like)
                ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', $like)));
        }
    }
}
