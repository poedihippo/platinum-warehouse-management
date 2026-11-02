<?php

namespace App\Http\Repositories\Permission;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\Permission\PermissionRepositoryInterface;
use App\Models\Permission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    public function __construct(Permission $model)
    {
        parent::__construct($model);
    }

    public function paginateIndex(mixed $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(Permission::class)
            ->allowedFilters([
                'name',
                AllowedFilter::scope('role_id', 'whereRoleId'),
            ])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->orderBy('id', 'DESC')
            ->paginate($perPage);
    }
}
