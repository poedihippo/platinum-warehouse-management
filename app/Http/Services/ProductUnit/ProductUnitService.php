<?php

namespace App\Http\Services\ProductUnit;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Imports\ProductUnitSeederImport;
use App\Interfaces\Repositories\ProductUnit\ProductUnitRepositoryInterface;
use App\Interfaces\Repositories\Stock\StockRepositoryInterface;
use App\Interfaces\Services\ProductUnit\ProductUnitServiceInterface;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProductUnitService extends BaseService implements ProductUnitServiceInterface
{
    public function __construct(
        protected ProductUnitRepositoryInterface $repository,
        protected StockRepositoryInterface $stockRepository,
    ) {
        parent::__construct($repository);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateIndex($perPage);
    }

    public function findForShow(mixed $id): ProductUnit
    {
        return $this->repository->findForShow($id);
    }

    public function updateProductUnit(ProductUnit $productUnit, mixed $data): ProductUnit
    {
        if ($productUnit->refer_id) {
            throw new BadRequestHttpException('Product Grouping can not update here');
        }

        $this->repository->update($productUnit, $data);

        return $productUnit;
    }

    public function createRelations(ProductUnit $productUnit, mixed $validated, mixed $relatedProductUnits): void
    {
        $this->ensureRelatedUnitsAreNotQr($relatedProductUnits);

        DB::transaction(function () use ($validated, $relatedProductUnits, $productUnit) {
            $data = array_merge(
                $productUnit->toArray(),
                $validated,
                [
                    'refer_id' => $productUnit->id,
                ]
            );

            unset($data['id'], $data['created_at'], $data['updated_at']);

            $groupingProductUnit = $this->repository->create($data);

            if ($relatedProductUnits) {
                $data = collect($relatedProductUnits)->map(fn ($req) => ['related_product_unit_id' => $req['id'], 'qty' => $req['qty']]);
                $groupingProductUnit->relations()->createMany($data);
            }
        });
    }

    public function updateRelations(ProductUnit $productUnit, mixed $id, mixed $validated, mixed $relatedProductUnits): void
    {
        $this->ensureRelatedUnitsAreNotQr($relatedProductUnits);

        $groupingProductUnit = $this->repository->findGroupingOrFail($id, $productUnit->id);

        DB::transaction(function () use ($groupingProductUnit, $validated, $relatedProductUnits, $productUnit) {
            $data = array_merge(
                $productUnit->toArray(),
                $validated,
            );
            unset($data['id'], $data['refer_id'], $data['created_at'], $data['updated_at']);

            $groupingProductUnit->update($data);
            $groupingProductUnit->relations()->delete();

            if ($relatedProductUnits) {
                $data = collect($relatedProductUnits)->map(fn ($req) => ['related_product_unit_id' => $req['id'], 'qty' => $req['qty']]);
                $groupingProductUnit->relations()->createMany($data);
            }
        });
    }

    public function paginateUserPrices(ProductUnit $productUnit, mixed $userId, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateUserPrices($productUnit, $userId, $perPage);
    }

    public function import(mixed $file): void
    {
        Excel::import(new ProductUnitSeederImport, $file);
    }

    public function findByStock(mixed $stockId): ?ProductUnit
    {
        $stock = $this->stockRepository->findForProductLookup($stockId);
        if (! $stock) {
            return null;
        }

        $productUnit = $this->repository->findForStockProductUnit($stock->stock_product_unit_id);

        if (! $productUnit) {
            return null;
        }
        $productUnit->expired_date = $stock->expired_date;

        return $productUnit;
    }

    public function paginateLoyaltyPoints(mixed $search, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateLoyaltyPoints($search, $perPage);
    }

    /**
     * Setting 0 removes the unit from the program — there is no delete
     * route, this is how "remove" works.
     */
    public function applyPoints(ProductUnit $productUnit, mixed $pointsPerUnit): void
    {
        // An ineligible unit must never get points, regardless of which
        // route this was reached through.
        if (! $productUnit->loyalty_eligible) {
            throw new ApiResponseException('Product unit ini tidak memenuhi syarat untuk program loyalty.', 422);
        }

        Log::info('Product unit points updated', [
            'product_unit_id' => $productUnit->id,
            'old_value' => $productUnit->getOriginal('points_per_unit'),
            'new_value' => $pointsPerUnit,
            'changed_by' => auth()->id(),
        ]);

        // points_per_unit is intentionally absent from ProductUnit::$fillable.
        $productUnit->points_per_unit = $pointsPerUnit;
        $productUnit->save();
    }

    public function searchForClaims(mixed $term): Collection
    {
        return $this->repository->searchForClaims($term);
    }

    private function ensureRelatedUnitsAreNotQr(mixed $relatedProductUnits): void
    {
        $relatedProductUnits = collect($relatedProductUnits);

        $totalProductUnitGenerateQr = $this->repository->countGenerateQr($relatedProductUnits->pluck('id')->all());

        if ($totalProductUnitGenerateQr > 0) {
            throw ValidationException::withMessages([
                'related_product_units' => ['Can not related to product unit that have is_generate_qr = true.'],
            ]);
        }
    }
}
