<?php

namespace App\Http\Services;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use App\Interfaces\Services\BaseServiceInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

abstract class BaseService implements BaseServiceInterface
{
    public function __construct(protected BaseRepositoryInterface $baseRepository)
    {
    }

    public function findAllPaginate(int $perPage = 15, ?Closure $query = null, mixed $allowedFilters = [], mixed $allowedIncludes = [], mixed $allowedSorts = [], mixed $allowedFields = [], mixed $isSimplePaginate = false): LengthAwarePaginator|Paginator
    {
        return $this->baseRepository->findAllPaginate($perPage, $query, $allowedFilters, $allowedIncludes, $allowedSorts, $allowedFields, $isSimplePaginate);
    }

    public function findAll(?Closure $query = null, mixed $allowedFilters = [], mixed $allowedIncludes = [], mixed $allowedSorts = [], mixed $allowedFields = []): Collection
    {
        return $this->baseRepository->findAll($query, $allowedFilters, $allowedIncludes, $allowedSorts, $allowedFields);
    }

    public function findById(mixed $id, ?Closure $query = null, mixed $load = []): mixed
    {
        return $this->baseRepository->findById($id, $query, $load);
    }

    public function findByIdOrFail(mixed $id, ?Closure $query = null, mixed $load = []): mixed
    {
        return $this->baseRepository->findByIdOrFail($id, $query, $load);
    }

    public function findTenanted(mixed $id, mixed $columns = ['*'], mixed $fail = true, ?Closure $query = null): mixed
    {
        return $this->baseRepository->findTenanted($id, $columns, $fail, $query);
    }

    public function create(mixed $data): Model
    {
        return $this->baseRepository->create($data);
    }

    public function update(Model $model, mixed $data): Model
    {
        return $this->baseRepository->update($model, $data);
    }

    public function delete(Model $model): ?bool
    {
        return $this->baseRepository->delete($model);
    }

    public function forceDelete(Model $model): ?bool
    {
        return $this->baseRepository->forceDelete($model);
    }

    public function restore(Model $model): bool
    {
        return $this->baseRepository->restore($model);
    }
}
