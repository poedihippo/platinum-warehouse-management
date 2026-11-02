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

    public function findAllPaginate(int $perPage = 15, ?Closure $query = null, mixed $allowedFilters = [], mixed $allowedIncludes = [], mixed $allowedSorts = [], mixed $allowedFields = [], mixed $isSimplePaginate = false): LengthAwarePaginator|Paginator;

    public function findAll(?Closure $query = null, mixed $allowedFilters = [], mixed $allowedIncludes = [], mixed $allowedSorts = [], mixed $allowedFields = []): Collection;

    public function findById(mixed $id, ?Closure $query = null, mixed $load = []): mixed;

    public function findByIdOrFail(mixed $id, ?Closure $query = null, mixed $load = []): mixed;

    public function findTenanted(mixed $id, mixed $columns = ['*'], mixed $fail = true, ?Closure $query = null): mixed;

    public function create(mixed $data): Model;

    public function update(Model $model, mixed $data): Model;

    public function delete(Model $model): ?bool;

    public function forceDelete(Model $model): ?bool;

    public function restore(Model $model): bool;
}
