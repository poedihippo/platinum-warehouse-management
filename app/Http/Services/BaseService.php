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

    public function findAllPaginate(int $perPage = 15, ?Closure $query = null, ?array $allowedFilters = [], ?array $allowedIncludes = [], ?array $allowedSorts = [], ?array $allowedFields = [], bool $isSimplePaginate = false): LengthAwarePaginator|Paginator
    {
        return $this->baseRepository->findAllPaginate($perPage, $query, $allowedFilters, $allowedIncludes, $allowedSorts, $allowedFields, $isSimplePaginate);
    }

    public function findAll(?Closure $query = null, ?array $allowedFilters = [], ?array $allowedIncludes = [], ?array $allowedSorts = [], ?array $allowedFields = []): Collection
    {
        return $this->baseRepository->findAll($query, $allowedFilters, $allowedIncludes, $allowedSorts, $allowedFields);
    }

    public function findById(int|string $id, ?Closure $query = null, ?array $load = []): ?Model
    {
        return $this->baseRepository->findById($id, $query, $load);
    }

    public function findByIdOrFail(int|string $id, ?Closure $query = null, ?array $load = []): Model
    {
        return $this->baseRepository->findByIdOrFail($id, $query, $load);
    }

    public function findTenanted(int|string $id, array $columns = ['*'], bool $fail = true, ?Closure $query = null): ?Model
    {
        return $this->baseRepository->findTenanted($id, $columns, $fail, $query);
    }

    public function create(array $data): Model
    {
        return $this->baseRepository->create($data);
    }

    public function update(Model $model, array $data): Model
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
