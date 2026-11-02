<?php

namespace App\Interfaces\Services\ProductUnit;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductUnitServiceInterface extends BaseServiceInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function findForShow(int|string $id): ProductUnit;

    public function updateProductUnit(ProductUnit $productUnit, array $data): ProductUnit;

    public function createRelations(ProductUnit $productUnit, array $validated, mixed $relatedProductUnits): void;

    public function updateRelations(ProductUnit $productUnit, int $id, array $validated, mixed $relatedProductUnits): void;

    public function paginateUserPrices(ProductUnit $productUnit, int $userId, int $perPage): LengthAwarePaginator;

    public function import(mixed $file): void;

    public function findByStock(string $stockId): ?ProductUnit;

    public function paginateLoyaltyPoints(?string $search, int $perPage): LengthAwarePaginator;

    public function applyPoints(ProductUnit $productUnit, mixed $pointsPerUnit): void;

    public function searchForClaims(?string $term): Collection;
}
