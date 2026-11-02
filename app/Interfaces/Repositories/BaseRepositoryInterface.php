<?php

namespace App\Interfaces\Repositories;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

interface BaseRepositoryInterface
{
    public function query(): Builder;

    public function tenanted(): Builder;

    public function findAllPaginate(int $perPage = 15, ?Closure $query = null, ?array $allowedFilters = [], ?array $allowedIncludes = [], ?array $allowedSorts = [], ?array $allowedFields = [], bool $isSimplePaginate = false): LengthAwarePaginator|Paginator;

    public function findAll(?Closure $query = null, ?array $allowedFilters = [], ?array $allowedIncludes = [], ?array $allowedSorts = [], ?array $allowedFields = []): Collection;

    public function findById(int|string $id, ?Closure $query = null, ?array $load = []): ?Model;

    public function findByIdOrFail(int|string $id, ?Closure $query = null, ?array $load = []): Model;

    public function findTenanted(int|string $id, array $columns = ['*'], bool $fail = true, ?Closure $query = null): ?Model;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): ?bool;

    public function forceDelete(Model $model): ?bool;

    public function restore(Model $model): bool;
}
