<?php

namespace App\Interfaces\Services\ProductUnit;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductUnitServiceInterface extends BaseServiceInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function findForShow(mixed $id): ProductUnit;

    public function updateProductUnit(ProductUnit $productUnit, mixed $data): ProductUnit;

    public function createRelations(ProductUnit $productUnit, mixed $validated, mixed $relatedProductUnits): void;

    public function updateRelations(ProductUnit $productUnit, mixed $id, mixed $validated, mixed $relatedProductUnits): void;

    public function paginateUserPrices(ProductUnit $productUnit, mixed $userId, int $perPage): LengthAwarePaginator;

    public function import(mixed $file): void;

    public function findByStock(mixed $stockId): ?ProductUnit;

    public function paginateLoyaltyPoints(mixed $search, int $perPage): LengthAwarePaginator;

    public function applyPoints(ProductUnit $productUnit, mixed $pointsPerUnit): void;

    public function searchForClaims(mixed $term): Collection;
}
