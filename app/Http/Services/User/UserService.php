<?php

namespace App\Http\Services\User;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\User\UserRepositoryInterface;
use App\Interfaces\Services\User\UserServiceInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService extends BaseService implements UserServiceInterface
{
    public function __construct(protected UserRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateIndex($perPage);
    }

    public function me(): ?User
    {
        return auth('sanctum')->user()?->load(['roles' => fn ($q) => $q->select('id', 'name')]);
    }

    public function createWithRelations(array $data, mixed $roleIds, mixed $warehouseIds): User
    {
        return DB::transaction(function () use ($data, $roleIds, $warehouseIds) {
            $user = $this->repository->create($data);
            $user->syncRoles($roleIds);
            $user->warehouses()->sync($warehouseIds);

            return $user;
        });
    }

    public function updateWithRelations(User $user, array $data, mixed $roleIds, mixed $warehouseIds): User
    {
        $this->repository->update($user, $data);

        $user->syncRoles($roleIds);
        $user->warehouses()->sync($warehouseIds);

        return $user;
    }

    public function forceDeleteById(int|string $id): void
    {
        $user = $this->repository->findWithTrashedOrFail($id);
        $user->forceDelete();
    }

    public function restoreById(int|string $id): User
    {
        $user = $this->repository->findWithTrashedOrFail($id);
        $user->restore();

        return $user;
    }
}
