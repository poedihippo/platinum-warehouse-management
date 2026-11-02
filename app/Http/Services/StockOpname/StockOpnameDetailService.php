<?php

namespace App\Http\Services\StockOpname;

use App\Exceptions\ApiResponseException;
use App\Interfaces\Repositories\StockOpname\StockOpnameRepositoryInterface;
use App\Interfaces\Repositories\StockProductUnit\StockProductUnitRepositoryInterface;
use App\Interfaces\Services\StockOpname\StockOpnameDetailServiceInterface;
use App\Models\StockOpnameDetail;
use App\Models\StockOpnameItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class StockOpnameDetailService implements StockOpnameDetailServiceInterface
{
    public function __construct(
        protected StockOpnameRepositoryInterface $stockOpnameRepository,
        protected StockProductUnitRepositoryInterface $stockProductUnitRepository,
    ) {
    }

    public function paginate(int $stockOpnameId, int $perPage): LengthAwarePaginator
    {
        $stockOpname = $this->stockOpnameRepository->findTenanted($stockOpnameId, ['id']);

        $query = StockOpnameDetail::where('stock_opname_id', $stockOpname->id)
            ->with('stockProductUnit.productUnit')
            ->withCount([
                'stockOpnameItems',
                'stockOpnameItems as total_scanned_qty' => fn ($q) => $q->where('is_scanned', 1),
            ]);

        return QueryBuilder::for($query)
            ->allowedSorts(['id', 'created_at'])
            ->allowedIncludes('stockOpname')
            ->paginate($perPage);
    }

    public function find(int $stockOpnameId, mixed $stockOpnameDetailId): StockOpnameDetail
    {
        $stockOpname = $this->stockOpnameRepository->findTenanted($stockOpnameId, ['id']);

        return $stockOpname->details()->where('id', $stockOpnameDetailId)
            ->with(['stockOpname', 'stockProductUnit.productUnit'])
            ->withCount([
                'stockOpnameItems',
                'stockOpnameItems as total_scanned_qty' => fn ($q) => $q->where('is_scanned', 1),
            ])
            ->firstOrFail();
    }

    public function create(int $stockOpnameId, mixed $productUnitId): StockOpnameDetail
    {
        $stockOpname = $this->stockOpnameRepository->findTenanted($stockOpnameId, ['id', 'warehouse_id']);
        $stockProductUnit = $this->stockProductUnitRepository->query()->select('id')
            ->where('warehouse_id', $stockOpname->warehouse_id)
            ->where('product_unit_id', $productUnitId)
            ->firstOrFail();

        return $stockOpname->details()->create([
            'stock_product_unit_id' => $stockProductUnit->id,
            'qty' => $stockProductUnit->stocks->count() ?? 0,
        ]);
    }

    public function updateDetail(int $stockOpnameId, mixed $id, array $data): mixed
    {
        $stockOpname = $this->stockOpnameRepository->findTenanted($stockOpnameId, ['id']);

        return $stockOpname->details()->where('id', $id)->update($data);
    }

    /**
     * Scanning a group parent also marks its children's items as scanned.
     */
    public function scan(int $stockOpnameId, mixed $stockOpnameDetailId, mixed $stockId, mixed $isScanned): string
    {
        $stockOpname = $this->stockOpnameRepository->findTenanted($stockOpnameId, ['id']);
        $stockOpnameDetail = $stockOpname->details()->where('id', $stockOpnameDetailId)->first();
        if (! $stockOpnameDetail) {
            throw new ApiResponseException('Data stock opname tidak cocok', 400);
        }

        $stockOpnameItem = $stockOpnameDetail->stockOpnameItems()->where('stock_id', $stockId)->first();
        if (! $stockOpnameItem) {
            throw new ApiResponseException('QR tidak sesuai dengan data stock opname', 400);
        }

        $isScanned = $isScanned ?? 1;
        $stockOpnameItem->is_scanned = $isScanned;
        if (! $stockOpnameItem->isDirty('is_scanned') && $stockOpnameItem->is_scanned == 1) {
            throw new ApiResponseException('Stock sudah di scan', 400);
        }

        $stockOpnameItem->save();

        $message = 'Stock berhasil di scan';
        $stock = $stockOpnameItem->stock;
        if ($stock->childs->count() > 0) {
            StockOpnameItem::whereIn('stock_id', $stock->childs->pluck('id'))->update(['is_scanned' => $isScanned]);
            $message = 'Stock dan childs berhasil di scan';
        }

        return $message;
    }

    public function setDone(int $stockOpnameId, mixed $id, mixed $isDone): StockOpnameDetail
    {
        $stockOpname = $this->stockOpnameRepository->findTenanted($stockOpnameId, ['id']);
        $stockOpnameDetail = $stockOpname->details()->where('id', $id)->first();
        if (! $stockOpnameDetail) {
            throw new ApiResponseException('Data stock opname tidak cocok', 400);
        }

        $stockOpnameDetail->update([
            'is_done' => $isDone,
            'done_at' => now(),
        ]);

        return $stockOpnameDetail;
    }
}
