<?php

namespace App\Http\Repositories\User;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\User\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(User::with(['roles' => fn ($q) => $q->select('id', 'name')]))
            ->allowedIncludes(AllowedInclude::callback('warehouses', function ($query) {
                $query->select('id', 'code', 'name');
            }))
            ->allowedFilters([
                'email',
                'phone',
                'type',
                AllowedFilter::exact('company'),
                AllowedFilter::callback('name', fn ($q, $value) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$value.'%')->orWhere('phone', 'like', '%'.$value.'%')->orWhere('code', 'like', '%'.$value.'%'))),
            ])
            ->allowedSorts(['name', 'email', 'phone', 'type'])
            ->paginate($perPage);
    }

    public function findByEmail(mixed $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    public function findWithTrashedOrFail(int|string $id): User
    {
        return $this->query()->withTrashed()->findOrFail($id);
    }
}
