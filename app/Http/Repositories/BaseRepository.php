<?php

namespace App\Http\Repositories;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\QueryBuilder;

abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model)
    {
    }

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function tenanted(): Builder
    {
        return $this->model->newQuery()->tenanted();
    }

    public function findAllPaginate(int $perPage = 15, ?Closure $query = null, mixed $allowedFilters = [], mixed $allowedIncludes = [], mixed $allowedSorts = [], mixed $allowedFields = [], mixed $isSimplePaginate = false): LengthAwarePaginator|Paginator
    {
        $builder = $this->queryBuilder($query, $allowedFilters, $allowedIncludes, $allowedSorts, $allowedFields);

        if ($isSimplePaginate) {
            return $builder->simplePaginate($perPage);
        }

        return $builder->paginate($perPage);
    }

    public function findAll(?Closure $query = null, mixed $allowedFilters = [], mixed $allowedIncludes = [], mixed $allowedSorts = [], mixed $allowedFields = []): Collection
    {
        return $this->queryBuilder($query, $allowedFilters, $allowedIncludes, $allowedSorts, $allowedFields)->get();
    }

    public function findById(mixed $id, ?Closure $query = null, mixed $load = []): mixed
    {
        $data = $this->applyQuery($this->query(), $query)->find($id);

        if ($data && count($load ?? [])) {
            $data->load($load);
        }

        return $data;
    }

    public function findByIdOrFail(mixed $id, ?Closure $query = null, mixed $load = []): mixed
    {
        $data = $this->applyQuery($this->query(), $query)->findOrFail($id);

        if (count($load ?? [])) {
            $data->load($load);
        }

        return $data;
    }

    public function findTenanted(mixed $id, mixed $columns = ['*'], mixed $fail = true, ?Closure $query = null): mixed
    {
        return $this->applyQuery($this->query(), $query)->findTenanted($id, $columns, $fail);
    }

    public function create(mixed $data): Model
    {
        return $this->model::create($data);
    }

    public function update(Model $model, mixed $data): Model
    {
        $model->update($data);

        return $model;
    }

    public function delete(Model $model): ?bool
    {
        return $model->delete();
    }

    public function forceDelete(Model $model): ?bool
    {
        return $model->forceDelete();
    }

    public function restore(Model $model): bool
    {
        return $model->restore();
    }

    protected function applyQuery(Builder $builder, ?Closure $query = null): Builder
    {
        if ($query) {
            return $query($builder) ?? $builder;
        }

        return $builder;
    }

    protected function queryBuilder(?Closure $query = null, mixed $allowedFilters = [], mixed $allowedIncludes = [], mixed $allowedSorts = [], mixed $allowedFields = []): QueryBuilder
    {
        $builder = QueryBuilder::for($this->applyQuery($this->query(), $query));

        if (count($allowedFields ?? [])) {
            $builder->allowedFields(...$allowedFields);
        }

        if (count($allowedFilters ?? [])) {
            $builder->allowedFilters(...$allowedFilters);
        }

        if (count($allowedSorts ?? [])) {
            $builder->allowedSorts(...$allowedSorts);
        }

        if (count($allowedIncludes ?? [])) {
            $builder->allowedIncludes(...$allowedIncludes);
        }

        return $builder;
    }
}
