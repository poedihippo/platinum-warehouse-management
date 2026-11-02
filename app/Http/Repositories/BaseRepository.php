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

    public function findAllPaginate(int $perPage = 15, ?Closure $query = null, ?array $allowedFilters = [], ?array $allowedIncludes = [], ?array $allowedSorts = [], ?array $allowedFields = [], bool $isSimplePaginate = false): LengthAwarePaginator|Paginator
    {
        $builder = $this->queryBuilder($query, $allowedFilters, $allowedIncludes, $allowedSorts, $allowedFields);

        if ($isSimplePaginate) {
            return $builder->simplePaginate($perPage);
        }

        return $builder->paginate($perPage);
    }

    public function findAll(?Closure $query = null, ?array $allowedFilters = [], ?array $allowedIncludes = [], ?array $allowedSorts = [], ?array $allowedFields = []): Collection
    {
        return $this->queryBuilder($query, $allowedFilters, $allowedIncludes, $allowedSorts, $allowedFields)->get();
    }

    public function findById(int|string $id, ?Closure $query = null, ?array $load = []): ?Model
    {
        $data = $this->applyQuery($this->query(), $query)->find($id);

        if ($data && count($load ?? [])) {
            $data->load($load);
        }

        return $data;
    }

    public function findByIdOrFail(int|string $id, ?Closure $query = null, ?array $load = []): Model
    {
        $data = $this->applyQuery($this->query(), $query)->findOrFail($id);

        if (count($load ?? [])) {
            $data->load($load);
        }

        return $data;
    }

    public function findTenanted(int|string $id, array $columns = ['*'], bool $fail = true, ?Closure $query = null): ?Model
    {
        return $this->applyQuery($this->query(), $query)->findTenanted($id, $columns, $fail);
    }

    public function create(array $data): Model
    {
        return $this->model::create($data);
    }

    public function update(Model $model, array $data): Model
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

    protected function queryBuilder(?Closure $query = null, ?array $allowedFilters = [], ?array $allowedIncludes = [], ?array $allowedSorts = [], ?array $allowedFields = []): QueryBuilder
    {
        $builder = QueryBuilder::for($this->applyQuery($this->query(), $query));

        if (count($allowedFields ?? [])) {
            $builder->allowedFields($allowedFields);
        }

        if (count($allowedFilters ?? [])) {
            $builder->allowedFilters($allowedFilters);
        }

        if (count($allowedSorts ?? [])) {
            $builder->allowedSorts($allowedSorts);
        }

        if (count($allowedIncludes ?? [])) {
            $builder->allowedIncludes($allowedIncludes);
        }

        return $builder;
    }
}
